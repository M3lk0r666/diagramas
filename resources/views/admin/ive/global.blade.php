@extends('layouts.ive')

@section('title', 'IVE · ' . $client->name . ' | Diagramas')

@section('content')

    {{--
    ╔══════════════════════════════════════════════════════════╗
    ║  Infrastructure Visualization Engine — Shell View       ║
    ║                                                          ║
    ║  Layout autónomo: sin sidebar ni topbar Laravel.        ║
    ║  100 vw × 100 vh, solo el canvas IVE.                   ║
    ║                                                          ║
    ║  La config se inyecta en data-config como JSON.         ║
    ║  React la lee en main.jsx y la pasa a <App>.            ║
    ╚══════════════════════════════════════════════════════════╝
    --}}

    <div
        id="ive-root"
        style="width:100vw; height:100vh;"
        data-config="{{ json_encode([
            'clientId'   => $client->id,
            'clientName' => $client->name,
            'fullscreen' => true,
            'endpoints'  => [
                'global' => route('admin.ive.data.global', $client),
            ],
        ]) }}">
    </div>

@endsection
