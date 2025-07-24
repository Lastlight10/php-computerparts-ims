<?php
use Models\User;
require_once 'vendor/autoload.php';
require_once 'core/Logger.php';
require_once 'core/Connection.php';

class StaffController extends Controller {

  public function dashboard() {
    Logger::log("Reach Dashboard.");
    $this->view('staff/dashboard', $this->getUserInfoAndCount());
  }

  public function user_list() {
    Logger::log('Reached List of Users');
    $user_info = User::select('id', 'username', 'email', 'first_name', 'last_name','middle_name','birthdate','created_at','type')->get(); 
    $this->view('staff/user_list', ['user_info' => $user_info]);
  }

  public function products_list() {
    Logger::log('Reached List of Users');
    $user_info = User::select('id', 'sku', 'name', 'description', 'category_id','brand_id','unit_price','cost_price','current_stock','reorder_level','is_serialized','is_active','location_aisle','location_bin','created_at')->get(); 
    $this->view('staff/products_list', ['user_info' => $user_info]);
  }
}
