<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{
    /**
     * Index Page for this controller.
     *
     * Maps to the following URL
     * 		http://example.com/index.php/welcome
     *	- or -
     * 		http://example.com/index.php/welcome/index
     *	- or -
     * Since this controller is set as the default controller in
     * config/routes.php, it's displayed at http://example.com/
     *
     * So any other public methods not prefixed with an underscore will
     * map to /index.php/welcome/<method_name>
     *
     * @see http://codeigniter.com/user_guide/general/urls.html
     */
    public function __construct()
    {
        // Call the Model constructor
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Auth_model');
        $this->load->library('form_validation');
        $this->load->helper('form');
        $this->load->helper('url');
    }

    public function index()
    {
		if ($this->session->userdata('user_email')) 
		{
            redirect('dashboard', 'refresh');
        }
		else if(!empty ($_COOKIE['member_login']) && !empty ($_COOKIE['member_password']))
		{
			$data = array(
                'email' => $_COOKIE['member_login'],
                'password' => $_COOKIE['member_password'],
            );
			
			$result = $this->Auth_model->verifyLogIn($data);
			if ($result['valid']) 
			{
				$user_id = $result['user_id'];
				$user_email = $result['user_email'];
				$role_id = $result['role_id'];
				$out_id = $result['outlet_id'];
				$role = $this->db->get_where('user_roles', array('id' => $role_id))->row()->name;

				$userdata = array(
					'sessionid' => 'pos',
					'user_id' => $user_id,
					'user_email' => $user_email,
					'user_role' => $role_id,
					'user_outlet' => $out_id,
					'user_role_name'=>$role
				);
				
				$this->session->set_userdata($userdata);
				
				if($role === 'CUSTOMER_ROLE')
				{
					redirect(base_url().'appointments', 'refresh');
				} 
				else 
				{
					redirect(base_url().'dashboard', 'refresh');
				}
			} 
			else 
			{
				$this->session->set_flashdata('alert_msg', array('failure', 'Login', $result['error']));
				setcookie("member_login", "", time()-60*60*24*100, "/");
				setcookie("member_password", "", time()-60*60*24*100, "/"); 
				redirect(base_url());
			}
		}
		else 
		{
            $this->load->view('login', 'refresh');
        }
    }

	public function login()
    {
        if (isset($_POST['sp_login'])) {
            $data = array(
                'email' => $this->input->post('email'),
                'password' => $this->input->post('password'),
            );

            $em = $this->input->post('email');
            $ps = $this->input->post('password');

            if (empty($em)) {
                $this->session->set_flashdata('alert_msg', array('failure', 'Login', 'Please enter your username!'));
                redirect(base_url());
            } elseif (empty($ps)) {
                $this->session->set_flashdata('alert_msg', array('failure', 'Login', 'Please enter your password!'));
                redirect(base_url());
            } else {
                $result = $this->Auth_model->verifyLogIn($data);
                 if ($result['valid']) {
                    $user_id = $result['user_id'];
                    $user_email = $result['user_email'];
                    $role_id = $result['role_id'];
                    $out_id = $result['outlet_id'];
                    $role = $this->db->get_where('user_roles', array('id' => $role_id))->row()->name;

                    $userdata = array(
                        'sessionid' => 'pos',
                        'user_id' => $user_id,
                        'user_email' => $user_email,
                        'user_role' => $role_id,
                        'user_outlet' => $out_id,
                        'user_role_name'=>$role
                    );

                    $this->session->set_userdata($userdata);
                     
					
					if(!empty($this->input->post('remember_me'))) {
						setcookie("member_login", $em, time()+60*60*24*100, "/");
						setcookie("member_password", $ps, time()+60*60*24*100, "/"); 
					}
					else
					{
						setcookie("member_login", "", time()-60*60*24*100, "/");
						setcookie("member_password", "", time()-60*60*24*100, "/"); 
					}
			
					if($role === 'CUSTOMER_ROLE'){
                        redirect(base_url().'appointments', 'refresh');
                    
                    } else {
                    redirect(base_url().'dashboard', 'refresh');
                    }
                } else {
                    $this->session->set_flashdata('alert_msg', array('failure', 'Login', $result['error']));
                    redirect(base_url());
                }
            }
        }
    }

    public function logout()
    {
        $this->session->sess_destroy();
		setcookie("member_login", "", time()-60*60*24*100, "/");
		setcookie("member_password", "", time()-60*60*24*100, "/"); 
        redirect(base_url());
    }

    // Function to get the client IP address
    public function get_client_ip()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
}
