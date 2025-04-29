<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ env( 'APP_NAME' ) }}</title>
    @vite( 'resources/css/app.css' )
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset( 'images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/fontawesome-free-6.3.0-web/css/all.min.css' ) }}"/>

</head>
<body>
<div class="flex items-center justify-center w-screen h-screen bg-gradient-to-t from-sky-300 via-sky-600 to-sky-900">

    <div class="flex flex-col items-center gap-8">

        <img class="w-52 mb-4" src="{{ asset( 'images/santacasa-horizontal-branco.svg' ) }}">
        @yield( 'content' )

    </div>

</div>
</body>
</html>
