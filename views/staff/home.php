<?php
// home.php - This file contains only the content to be injected into the main layout.
// It does NOT include <html>, <head>, or <body> tags.
?>
<style>
  .map-container {
          position: relative;
          width: 100%;
          padding-bottom: 56.25%; /* 16:9 Aspect Ratio (9 / 16 * 100) */
          height: 400px;
          overflow: hidden;
          border-radius: 0.5rem; /* Match card rounded corners */
      }
      .map-container iframe {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border: 0;
      }
</style>
<div class="bg-dark container max-w-4xl mx-auto space-y-8">

    <!-- Header Section -->
    <h1 class="text-4xl font-bold text-center text-white mb-8">Welcome to Computer IMS</h1>

    <p class="text text-left text-white" > Here we offer many services in our inventory management system on a web application. </p>

    <ul class="text text-left text-white">
      <li>Different User Statuses.</li>
      <li>Customer and Supplier Tracking.</li>
      <li>Product and Transactions inventory with Serial Number Integration.</li>
      <li>Simple and friendly user interface.</li>
    </ul>


    <!-- Mission Section -->
    <div class="bg-dark p-6 rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold light-txt py-2 mb-4 text-center">Our Mission</h2>
        <p class="light-txt py-2 leading-relaxed text-lg text-left">
            To provide cutting-edge computer hardware and software solutions,
            ensuring unparalleled quality and customer satisfaction. We are
            committed to fostering technological advancement and empowering
            individuals and businesses with reliable and efficient IT resources.
        </p>
    </div>

    <!-- Vision Section -->
    <div class="bg-dark p-6 rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold light-txt py-2 mb-4 text-center">Our Vision</h2>
        <p class="light-txt leading-relaxed text-lg text-left py-2">
            To be the leading provider of innovative computer solutions in the city,
            recognized for our integrity, expertise, and dedication to building a
            smarter, more connected future.
        </p>
    </div>

    <!-- Google Maps Location Section -->
    <div class="bg-dark p-6 rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold light-txt py-2 mb-4 text-center">Find Us Here</h2>
        <p class="light-txt py-2 mb-4 text-center">
            Visit our main office located at 4 Kaligayahan St, Quezon City, Metro Manila.
        </p>
        <div class="map-container px-3 py-2">
            <!-- Google Maps embed code provided by the user -->
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d910.7018149295934!2d121.09152795690258!3d14.688854236490322!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397ba103eb85f79%3A0x4ba5f39495734dda!2s4%20Kaligayahan%20St%2C%20Quezon%20City%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1753628751409!5m2!1sen!2sph"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
        <p class="light-txt py-2 text-sm mt-4 text-center">
            The map shows the location of 4 Kaligayahan St, Quezon City, Metro Manila.
        </p>
    </div>
    <div class="bg-dark p-6 rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold light-txt mb-4 text-center py-2">Contact Us:</h2>
        <p class="light-txt leading-relaxed text-lg text-left px-4 py-2">
            EMAIL: recon21342@gmail.com<br>
            PHONE: 09123456789<br>
            ADDRESS: 4 Kaligayahan St, Quezon City, Metro Manila
        </p>
    </div>

</div>
