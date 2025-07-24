<?php ob_start(); ?>
  <!-- Main Content -->
  <div class="main-content dark-bg">
    <div class="topbar d-flex justify-content-between align-items-center">
      <div class="light-txt"><strong>Welcome, <?= htmlspecialchars($username)?></strong></div>
      <div>
        <button class="btn btn-sm btn-outline-secondary">Logout</button>
      </div>
    </div>

    <div class="content light-txt">
      <h2>Dashboard</h2>
      <p>This is a simple Bootstrap 5 dashboard layout. Customize as needed.</p>

      <!-- Example cards -->
      <div class="row">
        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Products</h5>
              <p class="card-text">128 items in stock</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Transactions</h5>
              <p class="card-text">56 completed today</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Users</h5>
              <p class="card-text"><?= htmlspecialchars($count) ?> active users</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  ></script>

<?php
$content = ob_get_clean();
require_once 'staff_layout.php';

require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on dashboard.php");
?>