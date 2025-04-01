<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    @vite(['resources/sass/app.scss'])
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">

<div class="bg-white p-8 rounded shadow-lg w-96">
    <h2 class="text-2xl font-semibold text-center mb-6">Авторизация</h2>

    @if($errors->any())
        <div class="bg-red-200 text-red-700 p-4 rounded mb-4">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="mb-4">
            <label for="login" class="block text-sm font-medium text-gray-700">Логин</label>
            <input type="text" name="login" id="login" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>

        </div>

        <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700">Пароль</label>
            <input type="password" name="password" id="password" autocomplete="on" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
        </div>

        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Войти</button>
    </form>
</div>

</body>
</html>
