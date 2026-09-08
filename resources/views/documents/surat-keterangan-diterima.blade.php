@extends('documents._layout')

@section('isi')
    <table>
        <tr><th style="width:35%">Judul Form</th><td>{{ $submission->form?->title ?? '—' }}</td></tr>
        <tr><th>Ketua Pengaju</th><td>{{ $submission->submittedBy?->name ?? '—' }}</td></tr>
        <tr><th>Skema</th><td>{{ $submission->scheme?->name ?? '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $submission->status?->label() ?? '—' }}</td></tr>
    </table>
@endsection
