@extends('documents._layout')

@section('isi')
    <h2 style="font-size: 13px; margin: 12px 0 4px;">Informasi Pengajuan</h2>
    <table>
        <tr><th style="width:35%">Judul Pengajuan</th><td>{{ $submission->displayTitle() }}</td></tr>
        <tr><th>Ketua Pengaju</th><td>{{ $submission->submittedBy?->name ?? '-' }}</td></tr>
        <tr><th>Skema</th><td>{{ $submission->scheme?->name ?? '-' }}</td></tr>
        <tr><th>Status</th><td>{{ $submission->status?->label() ?? '-' }}</td></tr>
    </table>

    @php
        $reports = $submission->children()
            ->with(['formPhaseDetail.phaseType', 'formFieldResponses.formField'])
            ->where('is_submitted', true)
            ->orderBy('id')
            ->get();
    @endphp

    @forelse ($reports as $report)
        @php
            $cycleName = $report->formPhaseDetail ? \App\Queries\Monev\MonevCycles::nameOf($report->formPhaseDetail) : 'Laporan Monev';
            $answers = \App\Queries\Submission\ReadableSubmissionAnswers::run($report);
            $scores = \App\Queries\Review\VisibleReviewerScores::run($printedBy, $report);
        @endphp

        <h2 style="font-size: 13px; margin: 18px 0 4px;">{{ $cycleName }}</h2>
        <table>
            <tr><th style="width:35%">Status Laporan</th><td>{{ $report->status?->label() ?? 'Dikirim' }}</td></tr>
            <tr><th>Tanggal Dikirim</th><td>{{ $report->updated_at?->translatedFormat('d F Y H:i') ?? '-' }}</td></tr>
        </table>

        <h3 style="font-size: 12px; margin: 12px 0 4px;">Isian Capaian Kegiatan</h3>
        <table>
            @forelse ($answers as $answer)
                <tr>
                    <th style="width:35%">{{ $answer->label }}</th>
                    <td>
                        @if ($answer->isFile)
                            {{ $answer->value ? basename($answer->value) : '-' }}
                        @else
                            {{ $answer->value ?? '-' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" style="text-align: center; color: #777;">Tidak ada isian form.</td></tr>
            @endforelse
        </table>

        @if ($scores['visible'] && count($scores['scores']) > 0)
            <h3 style="font-size: 12px; margin: 12px 0 4px;">Hasil Evaluasi Penilai</h3>
            @foreach ($scores['scores'] as $score)
                <table style="margin-bottom: 8px;">
                    <tr>
                        <th style="width:35%">Penilai</th>
                        <td><strong>{{ $score['reviewer'] }}</strong> ({{ $score['version_label'] }})</td>
                    </tr>
                    @foreach ($score['answers'] as $fieldAnswer)
                        <tr>
                            <th>{{ $fieldAnswer['label'] }}</th>
                            <td>{{ $fieldAnswer['value'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endforeach
        @endif
    @empty
        <p style="margin-top: 12px; color: #777;">Belum ada laporan Monev yang disubmit.</p>
    @endforelse
@endsection
