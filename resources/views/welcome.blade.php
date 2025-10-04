@extends('layout')

@section('content')
<a class="btn btn-primary" href="{{ route('xero.connect') }}">
    Connect Xero
</a>

@if(!empty($refreshToken))
<a class="btn btn-success" href="{{ route('xero.refresh-tokens') }}">
    Refresh Tokens
</a>

<a class="btn btn-danger" href="{{ route('xero.disconnect') }}">
    Disconnect Xero
</a>

@foreach ($tenants as $tenant)
<a class="btn btn-primary" href="{{ route('xero.invoices.index', ['tenant_id' => $tenant->tenantId]) }}">
    Invoices - {{ $tenant->tenantName }}
</a>
@endforeach

<a class="btn btn-primary" href="{{ route('xero.connections.index') }}">
    Connections
</a>
@endif


@endsection
