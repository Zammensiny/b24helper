<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bitrix Dev</title>
    @vite(['resources/js/app.js', 'resources/sass/app.scss'])
</head>
<body class="flex flex-col min-h-screen">

<main class="flex-grow">
    @yield('content')
</main>

@if(Auth::check())
    <button class="my-5 bg-green-500 text-white px-4 py-2 rounded">Админская кнопка</button>
@endif

<footer class="text-white my-5">
    <div class="container mx-auto text-center">
        <a href="https://t.me/Zammensiny" target="_blank" class="flex justify-center text-blue-400 text-lg">
            <i class="fab fa-telegram text-3xl mr-2"></i>
            t.me/Zammensiny
        </a>
    </div>
</footer>

</body>
</html>
