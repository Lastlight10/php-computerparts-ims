<?php
namespace Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection; // Assuming you still use this for DB connection init if not handled by Eloquent

use Models\User;

// As previously discussed, 'vendor/autoload.php' should ideally be in your main application bootstrap
// require_once 'vendor/autoload.php'; // This should be handled by your application's entry point

class StaffUserController extends Controller {

    /**
     * Helper to get current user ID. Replace with your actual authentication method.
     * @return int|null
     */
    private function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Displays the form to add a new user.
     * Accessible via /staff/users/add
     *
     * @return void
     */
    public function add() {
        Logger::log('USER_ADD: Displaying new user form.');

        // Pass an empty User model for form binding, and user types
        $this->view('staff/users/add', [
            'user' => new User(),
            'user_types' => ['Staff', 'Manager', 'Admin'] // Assuming these are the allowed types
        ], 'staff');
    }

    /**
     * Handles the POST request to store a new user in the database.
     * Accessible via /staff/users/store
     *
     * @return void
     */
    public function store() {
        Logger::log('USER_STORE: Attempting to store new user.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("USER_STORE_ERROR: Invalid request method. Must be POST.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /staff/users/add');
            exit();
        }

        // 1. Retrieve Input Data
        $username    = trim($this->input('username'));
        $email       = trim($this->input('email'));
        $password    = $this->input('password');
        $confirm_password = $this->input('confirm_password');
        $first_name  = trim($this->input('first_name'));
        $last_name   = trim($this->input('last_name'));
        $middle_name = trim($this->input('middle_name'));
        $birthdate   = $this->input('birthdate');
        $type        = $this->input('type');

        // 2. Validation
        $errors = [];
        $allowed_types = ['Staff', 'Manager', 'Admin'];

        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($email)) $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if (empty($password)) $errors[] = 'Password is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
        if ($password !== $confirm_password) $errors[] = 'Password and Confirm Password do not match.';
        if (empty($first_name)) $errors[] = 'First Name is required.';
        if (empty($last_name)) $errors[] = 'Last Name is required.';
        if (empty($birthdate)) $errors[] = 'Birthdate is required.';
        if (!empty($birthdate) && !strtotime($birthdate)) $errors[] = 'Invalid birthdate format.';
        if (empty($type) || !in_array($type, $allowed_types)) $errors[] = 'Invalid user type selected.';

        // Check for unique username and email
        if (User::where('username', $username)->exists()) {
            $errors[] = 'Username already taken.';
        }
        if (User::where('email', $email)->exists()) {
            $errors[] = 'Email already registered.';
        }

        if (!empty($errors)) {
            Logger::log("USER_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $_SESSION['error_message'] = 'Please correct the following issues: ' . implode('<br>', $errors);
            // Pass back input data to re-populate form fields
            $this->view('staff/users/add', [
                'user' => (object)[
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'middle_name' => $middle_name,
                    'birthdate' => $birthdate,
                    'type' => $type,
                ],
                'user_types' => $allowed_types,
                'error' => $_SESSION['error_message'] // Pass error message directly to view
            ], 'staff');
            exit();
        }

        // 3. Create and Save User
        try {
            $user = new User();
            $user->username = $username;
            $user->email = $email;
            $user->password = $password; // The setPasswordAttribute mutator will hash this
            $user->first_name = $first_name;
            $user->last_name = $last_name;
            $user->middle_name = !empty($middle_name) ? $middle_name : null;
            $user->birthdate = $birthdate;
            $user->type = $type;
            // created_at and updated_at are handled by Eloquent timestamps

            $user->save();

            Logger::log("USER_STORE_SUCCESS: New user '{$user->username}' (ID: {$user->id}) added successfully.");
            $_SESSION['success_message'] = 'User added successfully!';
            header('Location: /staff/user_list'); // Redirect to user list
            exit();

        } catch (\Exception $e) {
            Logger::log("USER_STORE_DB_ERROR: Failed to add user - " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while adding the user. Please try again. ' . $e->getMessage();
            $this->view('staff/users/add', [
                'user' => (object)[
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'middle_name' => $middle_name,
                    'birthdate' => $birthdate,
                    'type' => $type,
                ],
                'user_types' => $allowed_types,
                'error' => $_SESSION['error_message'] // Pass error message directly to view
            ], 'staff');
            exit();
        }
    }

    /**
     * Displays the form to edit an existing user.
     * Accessible via /staff/users/edit/{id}
     *
     * @param int $id The ID of the user to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("USER_EDIT: Attempting to display edit form for user ID: $id");

        $user = User::find($id);

        if (!$user) {
            Logger::log("USER_EDIT_FAILED: User ID $id not found for editing.");
            $_SESSION['error_message'] = "User with ID {$id} not found.";
            header('Location: /staff/user_list'); // Redirect to user list
            exit();
        }

        $user_types = ['Staff', 'Manager', 'Admin']; // Assuming these are the allowed types

        Logger::log("USER_EDIT_SUCCESS: Displaying edit form for user ID: $id - {$user->username}");
        $this->view('staff/users/edit', [
            'user' => $user,
            'user_types' => $user_types
        ], 'staff');
    }

    /**
     * Handles the POST request to update an existing user in the database.
     * Accessible via /staff/users/update
     *
     * @return void
     */
    public function update() {
        Logger::log('USER_UPDATE: Attempting to update user.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("USER_UPDATE_ERROR: Invalid request method. Must be POST.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /staff/user_list');
            exit();
        }

        // 1. Retrieve Input Data
        $id          = $this->input('id');
        $username    = trim($this->input('username'));
        $email       = trim($this->input('email'));
        // Password fields removed from form, so no need to retrieve them from input
        // $password    = $this->input('password');
        // $confirm_password = $this->input('confirm_password');
        $first_name  = trim($this->input('first_name'));
        $last_name   = trim($this->input('last_name'));
        $middle_name = trim($this->input('middle_name'));
        $birthdate   = $this->input('birthdate');
        $type        = $this->input('type');

        $user = User::find($id);

        if (!$user) {
            Logger::log("USER_UPDATE_FAILED: User ID $id not found for update.");
            $_SESSION['error_message'] = "User not found for update.";
            header('Location: /staff/user_list');
            exit();
        }

        // 2. Validation
        $errors = [];
        $allowed_types = ['Staff', 'Manager', 'Admin'];

        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($email)) $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if (empty($first_name)) $errors[] = 'First Name is required.';
        if (empty($last_name)) $errors[] = 'Last Name is required.';
        if (empty($birthdate)) $errors[] = 'Birthdate is required.';
        if (!empty($birthdate) && !strtotime($birthdate)) $errors[] = 'Invalid birthdate format.';
        if (empty($type) || !in_array($type, $allowed_types)) $errors[] = 'Invalid user type selected.';

        // REMOVED: Password validation logic since fields are no longer in the form
        // if (!empty($password)) {
        //     if (strlen($password) < 8) $errors[] = 'New password must be at least 8 characters long.';
        //     if ($password !== $confirm_password) $errors[] = 'New password and Confirm New Password do not match.';
        // }

        // Check for unique username and email, excluding current user's own
        if (User::where('username', $username)->where('id', '!=', $id)->exists()) {
            $errors[] = 'Username already taken by another user.';
        }
        if (User::where('email', $email)->where('id', '!=', $id)->exists()) {
            $errors[] = 'Email already registered by another user.';
        }

        if (!empty($errors)) {
            Logger::log("USER_UPDATE_FAILED: Validation errors for User ID $id: " . implode(', ', $errors));
            $_SESSION['error_message'] = 'Please correct the following issues: ' . implode('<br>', $errors);
            // Pass back input data to re-populate form fields
            $this->view('staff/users/edit', [
                'user' => (object)[ // Create a dummy object or use the existing user, but populate with submitted data
                    'id' => $id,
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'middle_name' => $middle_name,
                    'birthdate' => $birthdate,
                    'type' => $type,
                ],
                'user_types' => $allowed_types,
                'error' => $_SESSION['error_message'] // Pass error message directly to view
            ], 'staff');
            exit();
        }

        // 3. Assign new values and check for changes
        $user->username = $username;
        $user->email = $email;
        $user->first_name = $first_name;
        $user->last_name = $last_name;
        $user->middle_name = !empty($middle_name) ? $middle_name : null;
        $user->birthdate = $birthdate;
        $user->type = $type;

        // REMOVED: Password update logic as fields are no longer in the form
        // if (!empty($password)) {
        //     $user->password = $password; // The setPasswordAttribute mutator will hash this
        // }

        if (!$user->isDirty()) {
            Logger::log("USER_UPDATE_INFO: User ID $id submitted form with no changes.");
            $_SESSION['success_message'] = 'No changes were made to the user account.';
            header('Location: /staff/user_list');
            exit();
        }

        try {
            $user->save();
            Logger::log("USER_UPDATE_SUCCESS: User '{$user->username}' (ID: {$user->id}) updated successfully.");
            $_SESSION['success_message'] = 'User updated successfully!';
            header('Location: /staff/user_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("USER_UPDATE_DB_ERROR: Failed to update user ID $id - " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while updating the user. Please try again. ' . $e->getMessage();
            $this->view('staff/users/edit', [
                'user' => (object)[ // Re-populate form with submitted data
                    'id' => $id,
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'middle_name' => $middle_name,
                    'birthdate' => $birthdate,
                    'type' => $type,
                ],
                'user_types' => $allowed_types,
                'error' => $_SESSION['error_message'] // Pass error message directly to view
            ], 'staff');
            exit();
        }
    }
}
