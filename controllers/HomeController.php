<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

class HomeController extends Controller {
    public function index() {
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('home', $data);
    }
}
