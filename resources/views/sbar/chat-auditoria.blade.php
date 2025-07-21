@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Auditoria do Chat</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Mensagem</th>
                <th>Ação</th>
                <th>Usuário</th>
                <th>Data/Hora</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach(\App\Models\ChatAuditoria::with('mensagem', 'usuario')->orderByDesc('dt_acao')->limit(100)->get() as $audit)
                <tr>
                    <td>
                        {{ \Illuminate\Support\Facades\Crypt::decryptString($audit->mensagem->mensagem ?? '') }}
                    </td>
                    <td>{{ $audit->acao }}</td>
                    <td>{{ $audit->usuario->name ?? $audit->usuario_id }}</td>
                    <td>{{ \Carbon\Carbon::parse($audit->dt_acao)->format('d/m/Y H:i') }}</td>
                    <td>{{ json_decode($audit->detalhes)->ip ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection