<?php
class LoginController extends Controller {
    public function login() {
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('login/login', $data);
    }

    public function forgotpass(){
        Logger::log("FORGOTPASS: Reached method.");
        $this->view('login/forgotpass');
    }
}
