<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bill_Numbering extends CI_Controller
{
    public function __construct()
    {
		parent::__construct();
        $this->load->library('session');
        $this->load->model('Customers_model');
        $this->load->model('Constant_model');
        $this->load->model('Bill_Numbering_model');
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

    public function bill_numbering()
    {
		
		$permisssion_url = 'bill_numbering';
		$permission = $this->Constant_model->getPermissionPageWise($permisssion_url);
                
		if($permission->view_menu_right == 0)
		{
			redirect('dashboard');
		}
		$data['getBrand'] = $this->Bill_Numbering_model->getBrand();
		$data['getSupplier'] = $this->Bill_Numbering_model->getSupplier();
		$data['getCategory'] = $this->Bill_Numbering_model->getCategory();
		$data['getSubCategory'] = $this->Bill_Numbering_model->getSubCategory();
        $this->load->view('bill_numbering', $data);
    }
	
	public function AddBillNumbering()
	{
		$loginUserId= $this->session->userdata('user_id');
		$loginData = $this->Constant_model->getDataOneColumn('users', 'id', $loginUserId);
		$data['UserLoginName'] =  $loginData[0]->fullname;
		$this->load->view('add_bill_numbering', $data);	
	}
	
	public function SubmitBillNumbering()
	{
		$data = array('user_id' =>$this->session->userdata('user_id'),
			'created_date' =>date('Y-m-d H:i:s'),	
			'auto_number_change' =>$this->input->post('auto_number_change'),	
			'change_daily' =>$this->input->post('dailyplay'),	
			'change_weekly' =>$this->input->post('weeklyplay'),	
			'change_monthly' =>$this->input->post('monthlyplay'),	
			'change_yearly' =>$this->input->post('yearlyplay'),	
			'sales_invoice' =>$this->input->post('invoicepause'),	
			'pos_bill' =>$this->input->post('pospause'),	
			'current_year' =>$this->input->post('current_year'),	
			'current_month' =>$this->input->post('current_month'),	
			'current_day' =>$this->input->post('current_day'),	
			'enter_starting_number' =>$this->input->post('enter_starting_number'),	
			'status' => '0',	
		);
		$success = $this->Bill_Numbering_model->SubmitBillNumbering($data);
		$this->session->set_flashdata('SUCCESSMSG', 'Bill Numbering Added Successfully!!');
		$json['success'] = $success;
		echo json_encode($json);
	}
	
}
