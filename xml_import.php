<?php
require_once 'config.php';
requireAdmin();
require_once 'classes/Patient.php';
$msg = '';
if ($_FILES && $_FILES['xmlfile']['error'] == 0) {
    $patient = new Patient();
    $count = $patient->importFromXML($_FILES['xmlfile']['tmp_name']);
    $msg = "Imported $count patients.";
}
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Import XML</title>
    <link rel="stylesheet" href="assets/style.css">
  </head>
  <body>
    <div class="container">
      <header>
       <h1>Import Patients from XML</h1><a href="dashboard.php" class="btn primary">Back</a>
      </header>
      <main>
        <div class="card"><?php if($msg): ?>
          <div class="alert success">
           <?= $msg ?>
          </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
             <label>XML File</label><input type="file" name="xmlfile" accept=".xml" required>
            </div>
            <button type="submit" class="btn primary">Upload & Import</button>
          </form>
          <h3>XML Format Example:</h3>
          <pre>
            &lt;patients&gt;
            &lt;patient&gt;
            &lt;fullname&gt;John Doe&lt;/fullname&gt;
            &lt;age&gt;30&lt;/age&gt;
            &lt;gender&gt;Male&lt;/gender&gt;
            &lt;address&gt;123 Main St&lt;/address&gt;
            &lt;contact_number&gt;09123456789&lt;/contact_number&gt;
            &lt;/patient&gt;
            &lt;/patients&gt;
          </pre>
        </div>
      </main>
    </div>
  </body>
</html>