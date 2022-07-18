<body bgcolor="skyblue">
    <h1>user list</h1>
    <table border="1">

        <tr>
            <td>id</td>
            <td>name </td>
            <td>dist</td>


        </tr>
        <tr>
           
            @foreach ($member as $data)
           
            <td>{{$data['id']}}</td>
            <td>{{$data['name']}} </td>
            <td>{{$data['dist']}}</td>
            <form action="">
                <td><a href={{"/delete/" .$data['id']}}>delete</a></td>
            </form>
            <td><a href={{"/corection/" .$data['id']}}>edit</a></td>


        </tr>
        @endforeach
       
    </table>

    <body>
        <div>
            <br>
            <b> <a href="/home">Go_to_Home_page </a></b>
        </div>

    </body>
</body>