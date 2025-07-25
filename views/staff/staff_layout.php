<!DOCTYPE html>
<html>
<head>
  <title>COMP IMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Your custom CSS -->
  <link href="/resources/css/dashboard.css" rel="stylesheet">
  <link href="/resources/css/tables.css" rel="stylesheet">
  <link href="/resources/css/staff.css" rel="stylesheet">

</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



<div class="container-fluid">
    <div class="row">
        <div class="col-sm-auto bg-light sticky-top">
            <div class="d-flex flex-sm-column flex-row flex-nowrap bg-light align-items-center sticky-top" style="height: 100vh;">
                <a href="#" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Home">
                    <i class="bi bi-house-door-fill fs-1"></i>
                </a>

                <!-- Scrollable nav container -->
                <div class="flex-grow-1 overflow-auto w-100 text-center">
                    <ul class="nav nav-pills nav-flush flex-sm-column flex-row flex-nowrap mb-auto mx-auto align-items-center">

                        <li>
                            <a href="/staff/dashboard" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                                <i class="bi bi-clipboard-data fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/brands_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Brands">
                                <i class="bi bi-bootstrap fs-1"></i>
                            </a>
                        <li>
                            <a href="/staff/categories_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Categories">
                                <i class="bi bi-grid-1x2 fs-1"></i>
                            </a>
                        <li>
                            <a href="/staff/transactions_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Transactions">
                                <i class="bi-table fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/products_list" class="nav-link py-3 px-2" data-bs-placement="right" data-bs-toggle="tooltip" title="Products">
                                <i class="bi bi-motherboard fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/customers_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Customers">
                                <i class="bi bi-bag-check fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/suppliers_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Suppliers">
                                <i class="bi bi-truck fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/user_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip"  data-bs-placement="right" title="Users">
                                <i class="bi-people fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/edit_user_account" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="User Settings" >
                                <i class="bi bi-person-gear fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/login/logout" class="nav-link py-3 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout" onclick="return confirmLogout()">
                                <i class="bi bi-door-open fs-1"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
        <div class="col-sm p-3 min-vh-100 dark-bg">
            <?php if (isset($content)) echo $content; ?>
        </div>
    </div>
</div>

  
<script>
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  const tooltipList = [...tooltipTriggerList].map(t => new bootstrap.Tooltip(t));
</script>

<script>
/**
 * Prompts the user for confirmation before logging out.
 * @returns {boolean} True if the user confirms, false otherwise.
 */
function confirmLogout() {
    return confirm("Are you sure you want to log out?");
}
</script>
</body>
</html>
