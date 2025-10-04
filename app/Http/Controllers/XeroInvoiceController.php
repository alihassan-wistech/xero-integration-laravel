<?php

namespace App\Http\Controllers;

use App\Services\XeroService;
use Illuminate\Http\Request;

class XeroInvoiceController extends Controller
{

    private XeroService $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    public function index(Request $request)
    {
        if (!$request->has('tenant_id')) abort(403, 'Tenant ID not provided');

        $tenant_id = $request->get('tenant_id');
        $invoices = $this->xeroService->getInvoices($tenant_id);
        return view('xero.invoices.index', compact('invoices'));
    }
}
