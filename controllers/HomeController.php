<?php

class HomeController extends Controller {
    public function index() {
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('home', $data);
    }
}
