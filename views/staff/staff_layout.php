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

</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



<div class="container-fluid">
    <div class="row">
        <div class="col-sm-auto bg-light sticky-top">
            <div class="d-flex flex-sm-column flex-row flex-nowrap bg-light align-items-center sticky-top" style="height: 100vh;">
                <a href="#" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Home">
                    <i class="bi-house fs-1"></i>
                </a>

                <!-- Scrollable nav container -->
                <div class="flex-grow-1 overflow-auto w-100 text-center">
                    <ul class="nav nav-pills nav-flush flex-sm-column flex-row flex-nowrap mb-auto mx-auto align-items-center">

                        <li>
                            <a href="/staff/dashboard" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Dashboard">
                                <i class="bi-speedometer2 fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Transactions">
                                <i class="bi-table fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/products_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Products">
                                <i class="bi-heart fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Customers">
                                <i class="bi bi-person-lines-fill fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="/staff/user_list" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="Users">
                                <i class="bi-people-fill fs-1"></i>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link py-3 px-2" data-bs-toggle="tooltip" title="User Settings">
                                <i class="bi bi-person-fill-gear fs-1"></i>
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

</body>
</html>
