<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Bank_accounts extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('ba_model');
        $this->load->model('bdt_model');
        $this->load->model('Constant_model');
        $this->load->model('Customers_model');
		$this->load->model('Pos_model');
        $this->load->library('form_validation');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->library('pagination');

        $settingResult = $this->db->get_where('site_setting');
        $settingData = $settingResult->row();

        $setting_timezone = $settingData->timezone;

        date_default_timezone_set("$setting_timezone");
		if($this->session->userdata('user_id') == "")
		{
			redirect(base_url());
		}
    }

    public function index()
    {
		 $permisssion_url = 'bank_accounts';
         
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                $permission_re_id=$permission->resource_id;
                $permisssion_sub_url = 'index';
                $permissionsub = $this->Constant_model->getPermissionSubPageWise($permission_re_id,$permisssion_sub_url);
               
		if($permissionsub->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		
		$data['getBankAccount']			= $this->ba_model->getBankAccount();
        $data['user_role']				= $this->session->userdata('user_role');
		
	    $this->load->view('includes/header');
	    $this->load->view('bank_accounts/ba', $data);
        $this->load->view('includes/footer');
    }
	
	
	public function getOutletWiseAmount() {
		
		$payment = '';
		$id = $this->input->post('payment');
		$getOutletWisePaymentMethod = $this->Constant_model->getOutletWisePaymentMethod($this->input->post('val'));
		$payment = '<option value="">Select Payment Method</option>';
		
		foreach ($getOutletWisePaymentMethod as $payment_outlet) 
		{
			$selected = '';
			if($payment_outlet->id == $id)
			{
				$selected = 'selected';
			}
					
			$payment .= "<option ".$selected." value='".$payment_outlet->id."' >".$payment_outlet->name."</option>";
		}
		
		$json['payment']	= $payment;
		echo json_encode($json);
	}
	
	
	
	public function changeReconcile()
	{
		$id = $this->input->post('id');
		$value = $this->input->post('value');
		$this->Constant_model->changeReconcile($id,$value);
		$data['success'] = true;
		echo json_encode($data);
	}
			
	
	
	public function print_bank_transaction()
	{
		$settingResult = $this->db->get_where('site_setting');
		$settingData = $settingResult->row();
		$data['setting_dateformat'] = $settingData->datetime_format;
		$data['setting_site_logo'] = $settingData->site_logo;

		$id = $this->input->get('id');
		$data['getTransaction']	= $this->ba_model->getBankTransactionPrint($id);
		
		$this->load->view('bank_accounts/print_bank_transaction', $data);
	}

	public function addba()
    {
		$user_id = $this->session->userdata('user_id');
		$permission_data = $this->Constant_model->getDataWhere('permissions',' user_id='.$user_id." and resource_id=(select id from modules where name='bank_accounts')");
		
		if(!isset($permission_data[0]->add_right)|| (isset($permission_data[0]->add_right) && $permission_data[0]->add_right!=1)){
			$this->session->set_flashdata('alert_msg', array('failure', 'Add Bank account', 'You can not add Bank account. Please ask administrator!'));
			redirect($this->agent->referrer());
		}
		
		$data['user_role']=   $this->session->userdata('user_role');
        $this->load->view('includes/header');
		$this->load->view('bank_accounts/add_ba');
        $this->load->view('includes/footer');
	}
	
	public function cheque_manager()
	{
		
		$permisssion_url = 'cheque_manager';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		$data['getChequeManager']	= $this->ba_model->getChequeManager();
		$data['getSupplierCheque']	= $this->ba_model->getSupplierCheque();
		$this->load->view('includes/header');
		$this->load->view('bank_accounts/cheque_manager',$data);
        $this->load->view('includes/footer');
	}
	
	public function insertBa()
    {
		
		$this->form_validation->set_rules('account_number', 'Account number', 'required|numeric|is_unique[bank_accounts.account_number]');
		$this->form_validation->set_rules('current_balance', 'Current Balance', 'numeric');
		$this->form_validation->set_rules('bank', 'Bank', 'required');
		if ($this->form_validation->run() == FALSE)
		{
			$this->addBa();
		}
		else 
		{
			$account_number = $this->input->post('account_number');
			$bank = $this->input->post('bank');
			$branch = $this->input->post('branch');
			$current_balance = $this->input->post('current_balance');
			$us_id = $this->session->userdata('user_id');
			$tm = date('Y-m-d H:i:s', time());


				$ins_data = array(
						  'account_number' => $account_number,
						  'bank' => $bank,
						  'branch' => $branch,
						  'current_balance'=>$current_balance,
						  'created_by' => $us_id,
						  'user_id' => $us_id,
						  'created' => $tm,
				);

				$insert_id  = $this->Constant_model->insertDataReturnLastId('bank_accounts', $ins_data);

				$payment['trans_type']       = 'dep';
				$payment['amount']           = $current_balance;
				$payment['account_number']   = $insert_id;
				$payment['bring_forword']	 = 0;
				$payment['transfer_status']  = 1;
				$this->db->insert('transactions', $payment);


				if ($insert_id) {
					$this->session->set_flashdata('alert_msg', array('success', 'Add Bank Account', "Successfully Added Bank Account : $account_number"));
					redirect(base_url().'bank_accounts');
				}
        }
    }
	
	public function editBa()
    {
		$user_id = $this->session->userdata('user_id');
		$permission_data = $this->Constant_model->getDataWhere('permissions',' user_id='.$user_id." and resource_id=(select id from modules where name='bank_accounts')");
		
		if(!isset($permission_data[0]->edit_right)|| (isset($permission_data[0]->edit_right) && $permission_data[0]->edit_right!=1)){
			$this->session->set_flashdata('alert_msg', array('failure', 'Edit bank account', 'You can not edit bank account. Please ask administrator!'));
				redirect($this->agent->referrer());
		}
		
        $id = $this->input->get('id');
        $data['user_role'] =   $this->session->userdata('user_role');
		$data['editBank'] = $this->ba_model->editBank($id);
        $this->load->view('includes/header');
        $this->load->view('bank_accounts/edit_ba', $data);
        $this->load->view('includes/footer');
	}
	
	public function updateBa()
    {
        $id = $this->input->post('id');
        $account_number = $this->input->post('account_number');
        $bank = $this->input->post('bank');
        $branch = $this->input->post('branch');
        $current_balance = $this->input->post('current_balance');
        $us_id = $this->session->userdata('user_id');
        $tm = date('Y-m-d H:i:s', time());
        if (empty($account_number)) {
                    $this->session->set_flashdata('alert_msg', array('failure', 'Add Bank Account', 'Please enter Account Number!'));
                    redirect(base_url().'bank_accounts/addBa');
        } 
		else 
		{
                     
            $upd_data = array(
                    'account_number' => $account_number,
                    'bank' => $bank,
                    'branch' => $branch,
                    'current_balance'=>$current_balance
            );

            $this->Constant_model->updateData('bank_accounts', $upd_data, $id);
            $this->session->set_flashdata('alert_msg', array('success', 'Update Bank Account', 'Successfully Updated Bank Account Detail!'));
			redirect(base_url().'bank_accounts');
        }
    }
	
	public function exportBa()
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
        $objPHPExcel->getActiveSheet()->setCellValue('A1', 'Bank Account Report');

        $objPHPExcel->getActiveSheet()->getStyle('A1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('B1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('C1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('D1')->applyFromArray($top_header_style);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->applyFromArray($top_header_style);

        $objPHPExcel->getActiveSheet()->setCellValue('A2', 'Bank Account');
        $objPHPExcel->getActiveSheet()->setCellValue('B2', 'Bank');
        $objPHPExcel->getActiveSheet()->setCellValue('C2', 'Branch');
        $objPHPExcel->getActiveSheet()->setCellValue('D2', 'Balance');
        $objPHPExcel->getActiveSheet()->setCellValue('E2', 'Created Date');
 
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


        $bankinfo = $data['getBankAccount'] = $this->ba_model->getBankAccount();
		foreach ($bankinfo as $info)
		{
			$objPHPExcel->getActiveSheet()->setCellValue("A$jj", $info->account_number);
            $objPHPExcel->getActiveSheet()->setCellValue("B$jj", $info->bank);
            $objPHPExcel->getActiveSheet()->setCellValue("C$jj", $info->branch);
            $objPHPExcel->getActiveSheet()->setCellValue("D$jj", $info->current_balance);
            $objPHPExcel->getActiveSheet()->setCellValue("E$jj", $info->created);
			
			$objPHPExcel->getActiveSheet()->getStyle("A$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("B$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("C$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("D$jj")->applyFromArray($account_value_style_header);
            $objPHPExcel->getActiveSheet()->getStyle("E$jj")->applyFromArray($account_value_style_header);
			 ++$jj;
		}
      
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Bank_Account_Report.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }
	
	
	public function balance(){
		
		$permisssion_url = 'balance';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		
	    $sess_arr = ci_getSession_data();
        $user_outlet= $sess_arr['user_outlet'];
        $user_role = $sess_arr['user_role'];
		$data['getOutlet'] = $this->bdt_model->getOutletUserWise($user_outlet, $user_role);
		
        $format_array = ci_date_format();
        $data['site_dateformat']		= $format_array['siteSetting_dateformat'];
        $data['dateformat']				= $format_array['dateformat'];
        $data['expense_categories']		= $this->Constant_model->getDataOneColumn('expense_categories', 'status', '1');
		
		$data['getPaymentMethod']		= $this->bdt_model->getPaymentMethod();
		$data['getBalanceSheet']		= $this->ba_model->getBalanceSheet();
		$data['bank_account']			= $this->bdt_model->getBankAccountNumber();
		$data['expensesOrderNumber']= $this->ba_model->getExpensesOrderNumber();
        $this->load->view('includes/header');
        $this->load->view('bank_accounts/balance', $data);
        $this->load->view('includes/footer');
    }
	
	public function bank_transaction(){
		$permisssion_url = 'balance';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
		
        if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		
	    $sess_arr = ci_getSession_data();
        $user_outlet= $sess_arr['user_outlet'];
        $user_role = $sess_arr['user_role'];
		$data['getOutlet'] = $this->bdt_model->getOutletUserWise($user_outlet, $user_role);
		
        $format_array = ci_date_format();
        $data['site_dateformat']		= $format_array['siteSetting_dateformat'];
		
		$data['getBankBalanceSheet']		= $this->ba_model->getBankTransactionSheet();
		
		$this->load->view('includes/header');
        $this->load->view('bank_accounts/bank_transaction', $data);
        $this->load->view('includes/footer');
    }
	
	
	public function receivedcheque() {
		
		$permisssion_url = 'receivedcheque';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		$data['orders_data'] = $this->ba_model->getReceivedCheque();
        $this->load->view('bank_accounts/receivedcheuqe',$data);
    }
	
	public function voucherdetail() 
	{
		
		$permisssion_url = 'voucherdetail';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		$data['orders_data'] = $this->ba_model->getVoucherDetail();
        $this->load->view('bank_accounts/voucher_detail',$data);
    }
	
	public function get_payment_outletwise()
	{
		$outlet_id=$this->input->post('outlet_id');
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


	public function view_bank_invoice() {
		if(!empty($this->input->get('id')))
		{
			$settingResult = $this->db->get_where('site_setting');
			$settingData = $settingResult->row();
			$data['setting_dateformat'] = $settingData->datetime_format;
			$data['setting_site_logo'] = $settingData->site_logo;
			$id = $this->input->get('id');
			$data['order_id'] = $id;
			$data['result'] = $this->ba_model->getVoucherDetail_print($id);
			$this->load->view('bank_accounts/print_voucher_invoice', $data);
		}
		else
		{
			redirect(base_url().'bank_accounts/voucherdetail');
		}
	}
	
	public function view_bank_invoice_a4()
	{
		if(!empty($this->input->get('id')))
		{
			$settingResult = $this->db->get_where('site_setting');
			$settingData = $settingResult->row();
			$data['setting_dateformat'] = $settingData->datetime_format;
			$data['setting_site_logo'] = $settingData->site_logo;
			$id = $this->input->get('id');
			$data['order_id'] = $id;
			$data['result'] = $this->ba_model->getVoucherDetail_print($id);
			$this->load->view('bank_accounts/print_voucher_invoice_a4', $data);
		}
		else
		{
			redirect(base_url().'bank_accounts/voucherdetail');
		}
	}
	
	
	function get_payment_name(){
		extract($_POST);
		$getPayMethodData = $this->Constant_model->getDataOneColumn('payment_method', 'id', $id);
		$getCustomerData = $this->Constant_model->getDataOneColumn('customers', 'id', $customer);
			
		$customerName = '';
		if(count($getCustomerData) == 1){
			$customerName = $getCustomerData[0]->fullname;
		}
			
		if (count($getPayMethodData) == 1) {
			$payMethod_name = $getPayMethodData[0]->name;
			$arr = array('status' => 1,
						'data' => $payMethod_name,
						'paidAmount'=>$paid,
						'customerName'=>$customerName,
			);
		 echo json_encode($arr);
		}
	}
	
	public function save_payment()
    {
		
		$total_amount	= $this->input->post('total_amount');
		$grand_amount	= $this->input->post('grand_amount');
		$customer_id	= $this->input->post('customer_id');
		$Outletdata_id	= $this->input->post('Outletdata_id');
		$order_id	= $this->input->post('order_id');
		$payment = $this->input->post('payment');
		$us_id = $this->session->userdata('user_id');
        $tm = date('Y-m-d H:i:s', time());
		
		$unpaid_amount	  = $grand_amount - $total_amount;
		
		if($total_amount > $grand_amount)
		{
			$deposite = $this->Customers_model->getCustomerDeposite($customer_id);
			$customerdeposite = $total_amount - $grand_amount;
			$finaldeposite = $deposite +  $customerdeposite;
			$deposite = $this->Customers_model->UpdateDeposite($finaldeposite,$customer_id);
			$total_amount = $grand_amount;
			$unpaid_amount	= 0;
		}
		
		
		
		$order_row = $this->db->get_where('orders_payment', array(
            'id' => $order_id
        ))->row();
		
        $get_grandtotal  = $order_row->paid_amt;
		
		$update_order_id = array(
            'paid_amt'		=> $total_amount+$get_grandtotal,
            'unpaid_amt'	=> $unpaid_amount,
            'updated_user_id' => $us_id,
            'updated_datetime' => $tm,
            "vt_status" => 1
        );
		
        $this->db->update('orders_payment', $update_order_id, array(
			'id' => $order_id
        ));
		
		foreach ($payment as $value)
		{
			$paid_amt       = $value['paid'];
			$payment_method = $value['paid_by'];
            
			$pay_query   = $this->db->get_where('payment_method', array(
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
				'trans_type'	=> 'dep',
				'outlet_id'		=> $Outletdata_id,
				'user_id'		=> $customer_id,
				'amount'		=> $paid_amt,
				'bring_forword'	=> $pay_balance,
				'account_number'=> $payment_method,
				'created_by'	=> $us_id,
				'cheque_number'	=> $value['cheque'],
				'cheque_date'	=> $value['cheque_date'],
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
	
	
	/*
	 *  Add expance
	 */
	
	 public function add_expenses_ajax()
    {
        $id = $this->input->post('id');
        $this->form_validation->set_rules('expense_no','Expense No','trim|required');
        $this->form_validation->set_rules('Outlets','Outlets','trim|required');
        $this->form_validation->set_rules('datee','date','trim|required');
        $this->form_validation->set_rules('Amount','Amount','trim|required');
        $this->form_validation->set_rules('Category','Category','trim|required');
        $this->form_validation->set_rules('payment','payment','trim|required');
        $this->form_validation->set_rules('Reason','Reason','trim|required');
        $this->form_validation->set_rules('entry_no','Entry No','trim|required');
        $this->form_validation->set_error_delimiters("<span class='label label-danger'>","</span>");
		
		$us_id = $this->session->userdata('user_id');
        if($this->form_validation->run()== false){
            $data = array(
                'expense_no' => form_error('expense_no'),
                'Outlets' => form_error('Outlets'),
                'datee' => form_error('datee'),
                'Amount' => form_error('Amount'),
                'Category' => form_error('Category'),
                'payment' => form_error('payment'),
                'Reason' => form_error('Reason'),
                'entry_no' => form_error('entry_no'),
                'status'=> FALSE
            );
            echo json_encode($data);
            die();
        }
		else
        {
            $outlet = $this->input->post('Outlets');
			$select_outlet = $this->db->get_where('outlets',array('id'=>$outlet))->row();
			$data = array(
				'expenses_number' => ($this->input->post('expense_no')),
				'outlet_id' => $this->input->post('Outlets'),
				'outlet_name' => $select_outlet->name,
				'date' => $this->input->post('datee'),
				'amount' => $this->input->post('Amount'),
				'expense_category' => $this->input->post('Category'),
				'reason' => $this->input->post('Reason'),
				'payment_type' => $this->input->post('payment'),
				'transaction_id_fk' => $this->input->post('entry_no'),
				'created_user_id' => $us_id,
				'status' => "1",
			);
			$this->db->insert('expenses',$data);
            echo json_encode(array("status" => TRUE));
        }
    }
}
