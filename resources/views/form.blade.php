<html>
<?php

// value="{{$edit_data['name']}}" 
// value="{{$edit_data['dist']}}" 
?>

<body bgcolor="skyblue">


    <form action="/pass" method="post">
        @csrf




        <label for="name">Name:</label>
        <input type="text" id="name" name="name" /><br><br>
        &nbsp &nbsp&nbsp<label for="dist">dist:</label>
        <input type="text" id="dist"  name="dist"  /><br><br>
        &nbsp&nbsp&nbsp&nbsp<button type="submit" name="submit" id="submit">Submit</button>
        <div>
            <br>

            <b> <a href="/retrive" class="btn">Table_Data</a></b>
        </div>




    </form>


</body>



</html>