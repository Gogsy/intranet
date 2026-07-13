<x-mail::message>
# Missing invoice entries — {{ \App\Support\InvoiceTracker\Months::name($month) }} {{ $year }}

The month has ended, but the following expected-monthly suppliers have no invoice entry yet:

<x-mail::table>
| Supplier | Contact |
|:---------|:--------|
@foreach ($suppliers as $supplier)
| {{ $supplier->name }} | {{ $supplier->email ?? '—' }} |
@endforeach
</x-mail::table>

Either the invoice hasn't arrived, it wasn't approved in SAP yet, or it simply wasn't entered here.

<x-mail::button :url="\App\Filament\Resources\InvoiceResource::getUrl('create')">
Enter an invoice
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
