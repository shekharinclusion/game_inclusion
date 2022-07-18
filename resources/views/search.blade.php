<!DOCTYPE html>
<html lang="en">
<head>
    
  
</head>
<body>
    <form action="get_data" method="post">
 <label  for="search" >search:</label>
<input type="search" name="search" id="search" /><br>
<br><button type="submit" name="submit">submit</button>

</form>

<?php

dd(@$data['name']);die;
?>

    
</body>
</html>