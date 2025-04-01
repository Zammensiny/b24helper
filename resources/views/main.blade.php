@extends('layouts.app')

@section('content')
    <div id="app"></div>

    <script>
        window.Laravel = {
            isAuthenticated: @json(Auth::check()),
            isAdmin: @json(Auth::check() && Auth::user()->is_admin),
        }
    </script>

@endsection
