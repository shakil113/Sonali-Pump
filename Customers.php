<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customers extends CI_Controller
{
    public function __construct()
    {
        // Call the Model constructor
        parent::__construct();
		
        $this->load->library('session');
        $this->load->model('Customers_model');
        $this->load->model('Constant_model');
        $this->load->model('Pos_model');
        $this->load->library('pagination');
		
        $settingResult		= $this->db->get_where('site_setting');
        $settingData		= $settingResult->row();
        $setting_timezone	= $settingData->timezone;
        date_default_timezone_set("$setting_timezone");
		require_once APPPATH.'third_party/PHPExcel.php';
		$this->excel = new PHPExcel(); 
		if ($this->session->userdata('user_id') == "") {
			redirect(base_url());
		}
    }
    
    public function view()
    {
		$permisssion_url = 'customers';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
		$permission_re_id=$permission->resource_id;
		$permisssion_sub_url = 'view';
		$permissionsub = $this->Constant_model->getPermissionSubPageWise($permission_re_id,$permisssion_sub_url);
		
		if($permissionsub->view_menu_right == 0)
		{
			redirect('dashboard');
		}
        
        $data['deposits']	= ci_customer_deposit();
		$data['getCreditColor'] = $this->Constant_model->getCreditColor();
        $data['results']	= $this->Customers_model->fetch_customers_data();
        $this->load->view('customers', $data);
    }
			
	public function credit_limits()
	{
		$permission = $this->Constant_model->getPermissionPageWise('credit_limits');
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		
		$data['getCreditLimit'] = $this->Customers_model->getCreditLimit();
		$data['getCreditColor'] = $this->Constant_model->getCreditColor();
		$data['getMaxSetLimitid'] = $this->Customers_model->getMaxSetLimitid();
		$data['getCustomer'] = $this->Constant_model->getCustomer();
        $this->load->view('credit_limits',$data);
	}
	
	public function SubmitColor()
	{
		$colours = $this->input->post('colours');
		if(!empty($colours))
		{
			foreach ($colours as $value)
			{
				$data = array('from' =>$value['from'],
					'to' =>$value['to'],
					'color' =>$value['color'],
					'created_by' =>$this->session->userdata('user_id'),
					'created_date' => date('Y-m-d H:i:s'),
					'updated_date' => date('Y-m-d H:i:s'),
				);
				$this->Customers_model->UpdateCreditColor($data,$value['id']);
			}
		}
		
		$json['success'] = true;
		echo json_encode($json);
	}
	
	public function getCustomerCredit()
	{
		$customer_id = $this->input->post('customer_id');
		$credit = $this->Customers_model->getCustomerCredit($customer_id);
		$json['credit'] = $credit;
		echo json_encode($json);
	}
	
	public function SubmitCreditLimit()
	{
		$array = array('customer_id' => $this->input->post('customer_id'),
			'credit_limit'	=> $this->input->post('creditlimit'),
			'new_limit'		=> $this->input->post('new_limit'),
			'reason'		=> $this->input->post('reason'),
			'created_by'	=> $this->session->userdata('user_id'),
			'created_date'	=> date('Y-m-d H:i:s'),
		);
		
		$this->Customers_model->UpdateNewCreditLimit($this->input->post('customer_id'),$this->input->post('new_limit'));
		$this->Customers_model->SubmitCreditLimit($array);
		$json['success'] = true;
		echo json_encode($json);
	}
		
	public function save_payment()
	{
		
		$customer_id	= $this->input->post('customer_id');
		$payment		= $this->input->post('payment');
		$grand_amount	= $this->input->post('grand_amount');
		$pay_amount		= $this->input->post('total_amount');
		$outlet_id		= $this->input->post('Outletdata_id');
		$us_id			= $this->session->userdata('user_id');
        $tm				= date('Y-m-d H:i:s', time());
		
		if($pay_amount > $grand_amount)
		{
			$deposite = $this->Customers_model->getCustomerDeposite($customer_id);
			$customerdeposite = $pay_amount - $grand_amount;
			$finaldeposite = $deposite +  $customerdeposite;
			$deposite = $this->Customers_model->UpdateDeposite($finaldeposite,$customer_id);
			$pay_amount = $grand_amount;
		}

		$orderdetail = $this->Customers_model->getUnpaidOrder($customer_id);
		foreach ($orderdetail as $value)
		{
			$unpaid = $value->unpaid_amt;
			$paid	= $value->paid_amt;
			if($unpaid <= $pay_amount)
			{
				$pay_amount  = $pay_amount - $unpaid;
				$finalPaidamount = $paid + $unpaid;
				$update_order_id = array(
					'paid_amt' => $finalPaidamount,
					'unpaid_amt' => 0,
					'updated_user_id' => $us_id,
					'updated_datetime' => $tm,
					"vt_status" => 1
				);
			
				$this->db->update('orders_payment', $update_order_id, array(
					'id' => $value->id
				));
			}
			else
			{
				if($pay_amount !=0)
				{
					$final_unpaid_amount = $unpaid - $pay_amount;
					$finalPaidamount = $paid + $pay_amount;
					
					$update_order_id = array(
						'paid_amt' => $finalPaidamount,
						'unpaid_amt' => $final_unpaid_amount,
						'updated_user_id' => $us_id,
						'updated_datetime' => $tm,
						"vt_status" => 1
					);
			
					$this->db->update('orders_payment', $update_order_id, array(
						'id' => $value->id
					));
					$pay_amount	= 0;
				}
			}
		}
	
		foreach ($payment as $value)
		{
			$paid_amt       = $value['paid'];
			$payment_method = $value['paid_by'];
            
			$pay_query = $this->db->get_where('payment_method', array(
                    'id' => $payment_method
            ))->row();
			
			$pay_balance = $pay_query->balance;
			$now_balance = $pay_balance + $paid_amt;
			$pay_data    = array(
				'balance' => $now_balance,
				'updated_user_id' => $us_id,
				'updated_datetime' => $tm
			);
			
			$this->db->update('payment_method', $pay_data, array(
					'id' => $payment_method
            ));
			
			$transaction_data = array(
				'trans_type'	=> 'payment',
				'user_id'		=> $customer_id,
				'outlet_id'		=> $outlet_id,
				'amount'		=> $paid_amt,
				'bring_forword'	=> $pay_balance,
				'account_number'=> $payment_method,
				'created_by'	=> $us_id,
				'cheque_number'	=> $value['cheque'],
				'cheque_date'	=> !empty($value['cheque_date'])?date('Y-m-d', strtotime($value['cheque_date'])):'',
				'bank'			=> $value['bank'],
				'card_number'	=> $value['addi_card_numb'],
				'created'		=> $tm
			);	
			$res1 = $this->Constant_model->insertDataReturnLastId('transactions', $transaction_data);	
			    
			if ($res1) {
				$response = array(
					'status' => 1,
					'message' => 'Your Payment successfully saved!!'
				);
			}
			else 
			{
				$response = array(
					'status' => 0,
					'message' => 'Due to some error please try again !!'
				);
			}
        }
		
		echo json_encode($response);
	}
	
	public function getOutletPayment()
	{
		$outlet_id = $this->input->post('outletid');
		$getOutletPayment = $this->Constant_model->getOutletWisePaymentMethod($outlet_id);
		$html = '';
		$html.='<option value="">Select Payment Method</option>';
		foreach ($getOutletPayment as $pay)
		{
			if ($pay->name == 'Cash' || $pay->name== 'Credit cards' || $pay->name == 'Cheque') {
				$html.='<option value="'.$pay->id.'">'.$pay->name.'</option>';
			}
		}
		
		$json['payment'] = $html;
		echo json_encode($json);
		
	}
	
	public function customer_transaction()
	{
		$customer_id = $this->input->get('cust_id');
		if($customer_id == "")
		{
			redirect('customers/view');
		}
		$data['customerdetail'] = $this->Constant_model->getCustomerDetail($customer_id);
		$data['results']	= $this->Customers_model->customer_transaction($customer_id);
		$this->load->view('customer_transaction', $data);
	}

	public function updateprepay()
    {
		$customer_id	= $this->input->post('cid');
        $payment_method = $this->input->post('payment_id');
        $payment		= $this->input->post('payment');
        $outlet_id		= $this->input->post('outlet_id');
        $us_id			= $this->session->userdata('user_id');
		
		$db_data = array(
			'customer_id' => $customer_id,
			'payment_method' => $payment_method,
			'outlet_id' => $outlet_id,
			'payment' => $payment, 
			'created_by' => $us_id,
			'created' => date('Y-m-d H:i:s'),
		);
		
		$balance_arr    = $this->Constant_model->getSingle('payment_method','balance','id='.$payment_method,'balance');
		$finalbalance_arr    = $balance_arr+$payment;
               
		$this->db->where('id', $payment_method);
		$this->db->update("payment_method", array('balance'=>$finalbalance_arr));
			
		$payment_arr = array('trans_type' => 'dep',
				'amount' =>$payment,
				'account_number' => $payment_method,
				'outlet_id' => $outlet_id,
				'user_id' => $us_id,
				'bring_forword' => $balance_arr,
				'created' => date('Y-m-d H:i:s'),
		);
		
		$this->Constant_model->insertDataReturnLastId('prepay', $db_data);
		$tid = $this->Constant_model->insertDataReturnLastId('transactions', $payment_arr);
		
		
		$this->db->query("UPDATE customers set deposit=deposit+$payment,tid=$tid WHERE id=$customer_id");    
		$this->session->set_flashdata('alert_msg', array('success', 'Update Prepayment', 'Successfully Insert Prepayment Record.'));
		redirect(base_url().'customers/prepay?customer_id='.$customer_id);
    }
	
	
	
//	public function updateprepay()
//    {
//		$id = strip_tags($this->input->post('id'));
//
//        $customer_id = strip_tags($this->input->post('cid'));
//        $payment_method = strip_tags($this->input->post('payment_method'));
//        $payment = strip_tags($this->input->post('payment'));
//        $us_id = $this->session->userdata('user_id');
//
//        if (empty($payment_method)) {
//            $this->session->set_flashdata('alert_msg', array('failure', 'Update Prepayment', 'Please enter Payment Method!'));
//            redirect(base_url().'customers/prepay?customer_id='.$customer_id);
//        } elseif (empty($payment)) {
//            $this->session->set_flashdata('alert_msg', array('failure', 'Update Prepayment', 'Please enter Payment!'));
//            redirect(base_url().'customers/prepay?customer_id='.$customer_id);
//         } else {
//             
//             
//            $db_data = array(
//                    'customer_id' => $customer_id,
//                    'payment_method' => $payment_method,
//                    'payment' => $payment, 
//                    'created_by' => $us_id,
//            );
//			
//			
//			
//            $records=  $this->db->where('id='.$id)->from('prepay')->count_all_results();
//            $tid =0;
//            if($records > 0){
//                $otid = $this->Constant_model->getSingle('customers','tid','id='.$customer_id,'tid');
//                $this->Constant_model->deleteData(transactions, $otid);
//            }
//            $payment_arr['outlet_id']=$this->Constant_model->getSingle('users','outlet_id','id='.$this->session->userdata('user_id'),'outlet_id');
//            
//            $payment_arr['trans_type']='dep';
//            $payment_arr['amount']=$payment;
//            $payment_arr['account_number']='0'.$payment_method;
//            $tid = $this->Constant_model->insertDataReturnLastId('transactions', $payment_arr);
//
//            if ($this->Constant_model->add_update('prepay','id='.$id,$db_data)) {
//            
//                $this->db->query("UPDATE customers set deposit=deposit+$payment,tid=$tid WHERE id=$customer_id");    
//                $this->session->set_flashdata('alert_msg', array('success', 'Update Prepayment', 'Successfully updated Prepayment Record.'));
//                redirect(base_url().'customers/prepay?customer_id='.$customer_id);
//            }
//        }
//    }
	
	public function import_customer()
	{
		$data['getOutlets']		= $this->Constant_model->getOutlets();
		$data['customer_group'] = $this->Constant_model->getCustomerGroup();
		$this->load->view('import_customer',$data);
	}
	
	public function get_payment_name()
	{
		extract($_POST);
        $paymenttypedetails  = $this->db->select('*')->where('id', $id)->get('payment_method')->row_array();
        $payMethod_name      = $paymenttypedetails['name'];
        $arr = array(
            'status' => 1,
            'data' => $payMethod_name,
            'paidAmount' => $this->input->post('tpaid')
        );
		
        echo json_encode($arr);
	}
	
	public function insert_import_customer()
	{
		$file_directory		= 'assets/product_import/';
		$new_file_name		= date("dmYHis").rand(000000, 999999).$_FILES["result_file"]["name"];
		move_uploaded_file($_FILES["result_file"]["tmp_name"], $file_directory . $new_file_name);

		$file_type	= PHPExcel_IOFactory::identify($file_directory . $new_file_name);
		$objReader	= PHPExcel_IOFactory::createReader($file_type);
		$objPHPExcel = $objReader->load($file_directory . $new_file_name);
		$sheet_data	= $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
		$i = 0;
		
		$outlet_id = $this->input->post('outlet_id');
		
		foreach ($sheet_data as $data)
		{
			if($i != 0)
			{
				if(!empty($data['B']) && !empty($data['A']))
				{
					$ckEmailData = $this->Constant_model->getDataOneColumn('customers', 'email', trim($data['B']));
					if (count($ckEmailData) == 0) {
					$array = array(
						'fullname'			=> $data['A'],
						'email'				=> $data['B'],
						'password'			=> $data['C'],
						'mobile'			=> $data['D'],
						'address'			=> $data['E'],
						'outlet_id'			=> $outlet_id,
						'customer_group'	=> $this->input->post('customer_group'),
						'outstanding'		=> str_replace(",", "", $data['F']),
						'nic'				=> $data['G'],
						'created_user_id'	=> $this->session->userdata('user_id'),
						'created_datetime'	=> date('Y-m-d H:i:s'),
						);
						
						$customer_id = $this->Constant_model->insertDataReturnLastId('customers', $array);
						if ($customer_id) {
							$paid_by = 6;
							
							$getOutletPayment = $this->Constant_model->getOutletWisePaymentMethod($outlet_id);
							foreach ($getOutletPayment as $pay)
							{
								if($pay->name == "Debit / Credit Sales")
								{
									$paid_by = $pay->id;
								}
							}
							
							$pay_query = $this->Constant_model->getPaymentIDName($paid_by);
							$pay_balance = $pay_query->balance;
							$now_balance = $pay_balance + str_replace(",", "", $data['F']);
							$pay_data = array(
								'balance' => $now_balance,
								'updated_user_id' => $this->session->userdata('user_id'),
								'updated_datetime' => date('Y-m-d H:i:s'),
							);
							$this->db->update('payment_method', $pay_data, array('id' => $paid_by));

							$transaction_data = array(
									'outlet_id' => $outlet_id,
									'user_id' => $customer_id,
									'trans_type' => 'outstanding',
									'account_number' => $paid_by,
									'amount' => str_replace(",", "", $data['F']),
									'bring_forword' => $pay_balance,
									'created_by' => $this->session->userdata('user_id'),
									'created' =>  date('Y-m-d H:i:s'),
							);	
							$this->Constant_model->insertDataReturnLastId('transactions', $transaction_data);

							$order_data = array(
									'outlet_id' => $outlet_id,
									'customer_id' => $customer_id,
									'customer_name' => $data['A'],
									'customer_email' => $data['B'],
									'customer_mobile' => $data['D'],
									'ordered_datetime' =>  date('Y-m-d H:i:s'),
									'subtotal' => str_replace(",", "", $data['F']),
									'grandtotal' => str_replace(",", "", $data['F']),
									'payment_method' => $paid_by,
									'payment_method_name' => 'Debit / Credit Sales',
									'unpaid_amt' => str_replace(",", "", $data['F']),
									'created_datetime' => date('Y-m-d H:i:s'),
									'customer_note' => 'outstanding'
							);	
							$this->Constant_model->insertDataReturnLastId('orders_payment', $order_data);
						}
					}
				}
			}
			$i++;
		}
		$this->session->set_flashdata('SUCCESSMSG', "Customer Added Successfully!!");
		redirect('customers/view');
		
	}
	

    public function edit_customer()
    {
		$default_store				 = $this->Pos_model->getDefaultCustomer();
		$data['default_customer_id'] =  $default_store->default_customer_id;
		
		
        $cust_id = $this->input->get('cust_id');
        $data['cust_id'] = $cust_id;
		$custDtaData = $this->Constant_model->getDataOneColumn('customers', 'id', $cust_id);
		if (count($custDtaData) == 0) {
			redirect(base_url());
		}
		$data['fullname'] = $custDtaData[0]->fullname;
		$data['email'] = $custDtaData[0]->email;
		$data['mobile'] = $custDtaData[0]->mobile;
		$data['customer_group'] = $custDtaData[0]->customer_group;
		
		$user_id = $this->session->userdata('user_id');
    	$permission_data = $this->Constant_model->getDataWhere('permissions',' user_id='.$user_id." and resource_id=(select id from modules where name='customers')");
    	
    	if(!isset($permission_data[0]->edit_right)|| (isset($permission_data[0]->edit_right) && $permission_data[0]->edit_right!=1)){
    		$this->session->set_flashdata('alert_msg', array('failure', 'Edit customers', 'You can not edit customers. Please ask administrator!'));
                redirect($this->agent->referrer());
    	}
        
        $data['group']=$this->db->where('is_active',1)->get('customer_group')->result();
		$this->load->view('edit_customer', $data);
    }


    public function customer_history()
    {
		
		$cust_id = $this->input->get('cust_id');
			$data['customer_history'] = $this->Customers_model->getOrderCustomerHistory($cust_id);
		
        $paginationData = $this->Constant_model->getDataOneColumn('site_setting', 'id', '1');
        $setting_dateformat = $paginationData[0]->datetime_format;
        $setting_currency = $paginationData[0]->currency;
       
		$custDtaData = $this->Constant_model->getDataOneColumn('customers', 'id', $cust_id);
		$data['order_payment'] = $this->Constant_model->getCustomerOderPayment($cust_id);
		if (count($custDtaData) == 0) {
			redirect(base_url());
		}

		$data['fullname']	= $custDtaData[0]->fullname;
		$data['email']		= $custDtaData[0]->email;
		$data['mobile']		= $custDtaData[0]->mobile;
		
		
        $data['cust_id'] = $cust_id;
        $data['dateformat'] = $setting_dateformat;
        $data['currency'] = $setting_currency;
        $this->load->view('customer_history', $data);
    }
	
	public function customer_sales_history_detail()
	{
		$paginationData = $this->Constant_model->getDataOneColumn('site_setting', 'id', '1');
        $setting_dateformat = $paginationData[0]->datetime_format;
        $setting_currency = $paginationData[0]->currency;
		
		$cust_id = $this->input->get('cust_id');
		$sales_id = $this->input->get('sales_id');
		$custDtaData = $this->Constant_model->getDataOneColumn('customers', 'id', $cust_id);
		if (count($custDtaData) == 0) {
			redirect(base_url());
		}
		$data['fullname']	= $custDtaData[0]->fullname;
		$data['email']		= $custDtaData[0]->email;
		$data['mobile']		= $custDtaData[0]->mobile;
		
        $data['cust_id'] = $cust_id;
        $data['dateformat'] = $setting_dateformat;
        $data['currency'] = $setting_currency;
		
      	$data['customer_history_item'] = $this->Customers_model->getOrderItemCustomerHistory($sales_id);
		$data['customer_history_payment'] = $this->Customers_model->getOrderPaymentCustomerHistory($sales_id);
		$this->load->view('customer_history_detail', $data);
	}
	
    public function addCustomer()
    {
        $user_id = $this->session->userdata('user_id');
    	$permission_data = $this->Constant_model->getDataWhere('permissions',' user_id='.$user_id." and resource_id=(select id from modules where name='customers')");
    	
    	if(!isset($permission_data[0]->add_right)|| (isset($permission_data[0]->add_right) && $permission_data[0]->add_right!=1)){
    		$this->session->set_flashdata('alert_msg', array('failure', 'Add customers', 'You can not Add customers. Please ask administrator!'));
				redirect($this->agent->referrer());
    	}
		
	
		$data['group']=$this->db->where('is_active',1)->get('customer_group')->result();
		$data['getOutlets'] = $this->Constant_model->getOutlets();
        $this->load->view('add_customer', $data);
    }

	// Insert New Customer;
	public function insertCustomer()
    {
		$user_id		= $this->session->userdata('user_id');
		$today			= date('Y-m-d H:i:s', time());
			
		$this->form_validation->set_rules('fullname', 'Name', 'required');
		$this->form_validation->set_rules('email', 'Email', 'required|trim|is_unique[customers.email]');
		$this->form_validation->set_rules('nic', 'NIC', 'required');
		$this->form_validation->set_rules('group', 'Group', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');
		$this->form_validation->set_rules('conpassword', 'Confirm Password', 'required|matches[password]');
		$this->form_validation->set_rules('outlet_id', 'Outlet', 'required');
		
		if ($this->form_validation->run() == FALSE)
		{
			$this->addCustomer();
		}
		else
		{
				$fullname	= $this->input->post('fullname');
				$email		= $this->input->post('email');
				$mobile		= $this->input->post('mobile');
				$password		= $this->input->post('password');
				$outstanding = $this->input->post('outstanding');
				$address	= $this->input->post('address');
				$nic		= $this->input->post('nic');
				$group		= $this->input->post('group');
				$outlet_id	= $this->input->post('outlet_id');
				$paid_by = 6;
				$getOutletPayment = $this->Constant_model->getOutletWisePaymentMethod($outlet_id);
				foreach ($getOutletPayment as $pay)
				{
					if($pay->name == "Debit / Credit Sales")
					{
						$paid_by = $pay->id;
					}
				}

				$us_id = $this->session->userdata('user_id');
				$tm = date('Y-m-d H:i:s', time());

					$ins_cust_data = array(
							  'fullname'		=> $fullname,
							  'email'			=> $email,
							  'mobile'			=> $mobile,
							  'password'		=> $password,
							  'created_user_id' => $us_id,
							  'created_datetime'=> $tm,
							  'nic'				=> $nic,
							  'outstanding'		=>$outstanding,
							  'address'			=>$address,
							  'outlet_id'		=>$outlet_id,
							  'customer_group'	=>$group,
					);
					$customer_id = $this->Constant_model->insertDataReturnLastId('customers', $ins_cust_data);
					if ($customer_id)
					{
						$pay_query = $this->Constant_model->getPaymentIDName($paid_by);
						$pay_balance = $pay_query->balance;
						$now_balance = $pay_balance + $outstanding;
						$pay_data = array(
							'balance' => $now_balance,
							'updated_user_id' => $user_id,
							'updated_datetime' => $today
						);
						$this->db->update('payment_method', $pay_data, array('id' => $paid_by));

						$transaction_data = array(
							'trans_type' => 'outstanding',
							'user_id'		=> $customer_id,
							'outlet_id'		 => $outlet_id,
							'account_number' => $paid_by,
							'amount'		 => $outstanding,
							'bring_forword'	 => $pay_balance,
							'created_by'	 => $user_id,
							'created'        => $today
						);	

						$this->Constant_model->insertDataReturnLastId('transactions', $transaction_data);

						$order_data = array(
								'outlet_id'			=> $outlet_id,
								'customer_id'		=> $customer_id,
								'customer_name'		=> $fullname,
								'customer_email'	=> $email,
								'customer_mobile'	=> $mobile,
								'ordered_datetime'	=> $today,
								'subtotal'			=> $outstanding,
								'grandtotal'		=> $outstanding,
								'payment_method'	=> $paid_by,
								'payment_method_name' => 'Debit / Credit Sales',
								'unpaid_amt'		=> $outstanding,
								'created_datetime'	=> $today,
								'customer_note'		=> 'outstanding'
						);	

						$this->Constant_model->insertDataReturnLastId('orders_payment', $order_data);

						$this->session->set_flashdata('alert_msg', array('success', 'Add Customer', "Successfully Added Customer : $fullname"));
						redirect(base_url().'customers/addCustomer');
					}
					else
					{
						$this->session->set_flashdata('alert_msg', array('failure', 'Add Customer', 'Please enter Customer Full Detail!'));
						redirect(base_url().'customers/addCustomer');
					}
				
		}
    }

    public function updateCustomer()
    {
        $cust_id = $this->input->post('cust_id');
        $fn		= $this->input->post('fullname');
        $email	= $this->input->post('email');
        $mb		= $this->input->post('mobile');
		$group	= $this->input->post('group');
		
        $us_id = $this->session->userdata('user_id');
        $tm = date('Y-m-d H:i:s', time());

        $upd_data = array(
			'fullname' => $fn,
			'email' => $email,
			'mobile' => $mb,
			'customer_group' => $group
        );
        $this->Constant_model->updateData('customers', $upd_data, $cust_id);
        $this->session->set_flashdata('alert_msg', array('success', 'Update Customer', 'Successfully Updated Customer Detail!'));
        redirect(base_url().'customers/edit_customer?cust_id='.$cust_id);
    }
	
	public function edit_customer_pass()
    {
        $cust_id = $this->input->get('cust_id');
		
        $data['group']=$this->db->where('is_active',1)->get('customer_group')->result();
        $data['cust_id'] = $cust_id;
        $this->load->view('edit_customer_pass', $data);
    }
	
    public function deleteCustomer()
    {
        $cust_id = $this->input->post('cust_id');
        $cust_fn = $this->input->post('cust_fn');

        if ($this->Constant_model->deleteData('customers', $cust_id)) {
            $this->session->set_flashdata('alert_msg', array('success', 'Delete Customer', "Successfully Deleted Customer : $cust_fn."));
            redirect(base_url().'customers/view');
        }
    }

    public function exportCustomer()
    {
        $siteSettingData = $this->Constant_model->getDataOneColumn('site_setting', 'id', '1');
        $site_dateformat = $siteSettingData[0]->datetime_format;
        $site_currency = $siteSettingData[0]->currency;

        $this->load->library('excel');

        require_once './application/third_party/PHPExcel.php';
        require_once './application/third_party/PHPExcel/IOFactory.php';

        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        $default_border = array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => '000000'),
        );

        $acc_default_border = array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => 'c7c7c7'),
        );
        $outlet_style_header = array(
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 10,
                'name' => 'Arial',
                'bold' => true,
            ),
        );
        $top_header_style = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 15,
                'name' => 'Arial',
                'bold' => true,
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
        );
        $style_header = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
                'bold' => true,
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            ),
        );
        $account_value_style_header = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            ),
        );
        $text_align_style = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
                'bold' => true,
            ),
        );

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:E1');
        $objPHPExcel->getActiveSheet()->setCellValue('A1', 'Customer Report');

        $objPHPExcel->getActiveSheet()->getStyle('A1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('B1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('C1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('D1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->applyFromArray($top_header_style);

        $objPHPExcel->getActiveSheet()->setCellValue('A2', 'Customer Full Name');
        $objPHPExcel->getActiveSheet()->setCellValue('B2', 'Customer Email');
        $objPHPExcel->getActiveSheet()->setCellValue('C2', 'Customer Mobile');
        $objPHPExcel->getActiveSheet()->setCellValue('D2', 'Total Order(s)');
        $objPHPExcel->getActiveSheet()->setCellValue('E2', "Total Amount Spent ($site_currency)");

        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($style_header);

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30);

        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(30);

        $jj = 3;
		
		$custDtaData = $this->Customers_model->fetch_customers_data();
		
        for ($t = 0; $t < count($custDtaData); ++$t) {
            $cust_id = $custDtaData[$t]->id;
            $cust_fn = $custDtaData[$t]->fullname;
            $cust_em = $custDtaData[$t]->email;
            $cust_mb = $custDtaData[$t]->mobile;

            if (empty($cust_em)) {
                $cust_em = '-';
            }
            if (empty($cust_mb)) {
                $cust_mb = '-';
            }

            $total_ordered_qty = 0;
            $total_ordered_amt = 0;

            $orderData = $this->Constant_model->getDataOneColumn('orders_payment', 'customer_id', $cust_id);
            for ($d = 0; $d < count($orderData); ++$d) {
                $order_grandTotal = $orderData[$d]->grandtotal;

                $total_ordered_amt += $order_grandTotal;

                ++$total_ordered_qty;
            }

            $objPHPExcel->getActiveSheet()->setCellValue("A$jj", "$cust_fn");
            $objPHPExcel->getActiveSheet()->setCellValue("B$jj", "$cust_em");
            $objPHPExcel->getActiveSheet()->setCellValue("C$jj", "$cust_mb");
            $objPHPExcel->getActiveSheet()->setCellValue("D$jj", "$total_ordered_qty");
            $objPHPExcel->getActiveSheet()->setCellValue("E$jj", "$total_ordered_amt");

            $objPHPExcel->getActiveSheet()->getStyle("A$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("B$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("C$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("D$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("E$jj")->applyFromArray($account_value_style_header);

            unset($cust_id);
            unset($cust_fn);
            unset($cust_em);
            unset($cust_mb);
            ++$jj;
        }
        unset($custDtaResult);
        unset($custDtaData);

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Customer_Report.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }

    public function exportCustomerHistory()
    {
        $cust_id = $this->input->get('cust_id');

        $siteSettingData = $this->Constant_model->getDataOneColumn('site_setting', 'id', '1');
        $site_dateformat = $siteSettingData[0]->datetime_format;
        $site_currency = $siteSettingData[0]->currency;

        $custDtaData = $this->Constant_model->getDataOneColumn('customers', 'id', "$cust_id");
        $cust_fn = $custDtaData[0]->fullname;

        $this->load->library('excel');

        require_once './application/third_party/PHPExcel.php';
        require_once './application/third_party/PHPExcel/IOFactory.php';

        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        $default_border = array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => '000000'),
        );

        $acc_default_border = array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => 'c7c7c7'),
        );
        $outlet_style_header = array(
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 10,
                'name' => 'Arial',
                'bold' => true,
            ),
        );
        $top_header_style = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 15,
                'name' => 'Arial',
                'bold' => true,
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
        );
        $style_header = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
                'bold' => true,
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            ),
        );
        $account_value_style_header = array(
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            ),
        );
        $text_align_style = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'bottom' => $default_border,
                'left' => $default_border,
                'top' => $default_border,
                'right' => $default_border,
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'ffff03'),
            ),
            'font' => array(
                'color' => array('rgb' => '000000'),
                'size' => 12,
                'name' => 'Arial',
                'bold' => true,
            ),
        );

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:G1');
        $objPHPExcel->getActiveSheet()->setCellValue('A1', "Sales History for : $cust_fn");

        $objPHPExcel->getActiveSheet()->getStyle('A1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('B1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('C1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('D1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('F1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('G1')->applyFromArray($top_header_style);
       
        $objPHPExcel->getActiveSheet()->setCellValue('A2', 'Sale Id');
        $objPHPExcel->getActiveSheet()->setCellValue('B2', 'Type');
        $objPHPExcel->getActiveSheet()->setCellValue('C2', 'Date & Time');
        $objPHPExcel->getActiveSheet()->setCellValue('D2', 'Outlet Name');
        $objPHPExcel->getActiveSheet()->setCellValue('E2', 'Total Qty');
        $objPHPExcel->getActiveSheet()->setCellValue('F2', "Sub Total ($site_currency)");
        $objPHPExcel->getActiveSheet()->setCellValue('G2', "Grand Total ($site_currency)");
        

        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('F2')->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle('G2')->applyFromArray($style_header);
        

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        

        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(30);

        $jj = 3;

        $total_qty = 0;
        $tpaid = 0;
        $totalamount = 0;
		
		$customer_history = $this->Customers_model->getOrderCustomerHistory($cust_id);
		foreach ($customer_history as $value)
		{
			$total_qty = $total_qty + $value->totalqty;
			$tpaid = $tpaid + $value->tpaid;
			$totalamount = $totalamount + $value->totalamount;
			
			if($value->status == '1')
			{
				$type = "Sale";
			}
			else
			{
				$type = "Return";
			}
			$objPHPExcel->getActiveSheet()->setCellValue("A$jj", $value->id);
			$objPHPExcel->getActiveSheet()->setCellValue("B$jj", $type);
			$objPHPExcel->getActiveSheet()->setCellValue("C$jj", $value->created_at);
			$objPHPExcel->getActiveSheet()->setCellValue("D$jj", $value->outlet_name);
            $objPHPExcel->getActiveSheet()->setCellValue("E$jj", $value->totalqty);
            $objPHPExcel->getActiveSheet()->setCellValue("F$jj", $value->tpaid);
            $objPHPExcel->getActiveSheet()->setCellValue("G$jj", $value->totalamount);
        
            $objPHPExcel->getActiveSheet()->getStyle("A$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("B$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("C$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("D$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("E$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("F$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("G$jj")->applyFromArray($account_value_style_header);
			$jj++;
		}
		
		$objPHPExcel->setActiveSheetIndex(0)->mergeCells("A$jj:D$jj");
        $objPHPExcel->getActiveSheet()->setCellValue("A$jj", 'Total');
        $objPHPExcel->getActiveSheet()->setCellValue("E$jj", "$total_qty");
        $objPHPExcel->getActiveSheet()->setCellValue("F$jj", "$tpaid ($site_currency)");
        $objPHPExcel->getActiveSheet()->setCellValue("G$jj", "$totalamount ($site_currency)");
        
        

        $objPHPExcel->getActiveSheet()->getStyle("A$jj")->applyFromArray($text_align_style);
        $objPHPExcel->getActiveSheet()->getStyle("B$jj")->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle("C$jj")->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle("D$jj")->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle("E$jj")->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle("F$jj")->applyFromArray($style_header);
        $objPHPExcel->getActiveSheet()->getStyle("G$jj")->applyFromArray($style_header);
        

        $objPHPExcel->getActiveSheet()->getRowDimension("$jj")->setRowHeight(30);
		
		header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Sales_History_for_'.$cust_fn.'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }

	public function addcustomergroup($id=0)
    {
        $user_id = $this->session->userdata('user_id');
    	$permission_data = $this->Constant_model->getDataWhere('permissions',' user_id='.$user_id." and resource_id=(select id from modules where name='customers')");
    	
    	if(!isset($permission_data[0]->add_right)|| (isset($permission_data[0]->add_right) && $permission_data[0]->add_right!=1)){
    		$this->session->set_flashdata('alert_msg', array('failure', 'Add customers', 'You can not add customers. Please ask administrator!'));
                redirect($this->agent->referrer());
    	}
        
        if($id>0){
            $data['edit']=$this->db->where('id',$id)->get('customer_group')->row_array();
        }else{
            $data['edit']=null;
        }
        $data['group']=$this->db->where('is_active',1)->get('customer_group')->result();
		
        $this->load->view('customer_group',$data);
    }

	public function delcustomergroup($id=0)
    {
        if ($id>0) {
            $array=array('is_active'=>0);
            $this->db->where('id',$id)->update('customer_group',$array);
            $this->session->set_flashdata('alert_msg', array('success', 'Delete Customer Grpoup', "Successfully Deleted Customer Group"));
            redirect(base_url().'customers/addcustomergroup');
        }
    }
    
    public function insertCustomergroup()
    {
        $name = $this->input->post('name');
        $id=$this->input->post('id');
        if (empty($name)) {
            $this->session->set_flashdata('alert_msg', array('failure', 'Add Customer Group', 'Please enter Customer Group Name!'));
            redirect(base_url().'customers/addcustomergroup');
			die();
        } 

		$ins_cust_data = array(
			'name' => $name
		);
		
		if ($id>0) {
			$this->db->where('id',$id)->update('customer_group',$ins_cust_data);
			$this->session->set_flashdata('alert_msg', array('success', 'Update Customer Group', "Successfully Customer group Update : $name"));
			redirect(base_url().'customers/addcustomergroup');
		} else {
			$this->db->insert('customer_group',$ins_cust_data);
			$this->session->set_flashdata('alert_msg', array('success', 'Add Customer Group', "Successfully Added Customer Group: $name"));
			redirect(base_url().'customers/addcustomergroup');
		}
    }
	
	
	public function customer_point_history()
    {
        $paginationData = $this->Constant_model->getDataOneColumn('site_setting', 'id', '1');
        $setting_dateformat = $paginationData[0]->datetime_format;
        $setting_currency = $paginationData[0]->currency;

		$cust_id = $this->input->get('cust_id');
		$result = $this->Constant_model->getDataOneColumn('orders_payment', 'customer_id', $cust_id);
		
        $data['historyData']= $result;
        $data['cust_id']	= $cust_id;
        $data['dateformat'] = $setting_dateformat;
        $data['currency']	= $setting_currency;

        $this->load->view('customer_point_history', $data);
    }
	
	
	public function prepay()
    {
		$id = $this->input->get('id');
        $customer_id = $this->input->get('customer_id');
	
        $data['customer_id'] = $customer_id;
        $data['getOutlet'] = $this->Constant_model->getOutlets();
		$data['resultdata'] = $this->Constant_model->getPrepayData();
		$this->load->view('prepay', $data);
    }
	
	public function getPaymentMethod()
	{
		$outlet_id = $this->input->post('outlet_id');
		$payment = $this->Constant_model->getOutletWisePaymentMethod($outlet_id);
		$html = '';
		$html.='<option value="">Select Payment Type</option>';
		foreach ($payment as $value)
		{
			$html.='<option value='.$value->id.'>'.$value->name.'</option>';
		}
		$data['success'] = $html;
		echo json_encode($data);
	}
		
}
