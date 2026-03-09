@php
    $raw = $reviewedBy ?? null;
    $entries = is_array($raw) ? $raw : [$raw];
    $entries = array_values(array_filter(array_map(function ($v) {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }, $entries)));
@endphp

@if (count($entries) === 1)
    <div class="signatory-compact-container">
        <div class="signatory-label">Reviewed By:</div>
        <div class="signatory-signature-line">{{ $entries[0] }}</div>
        <div class="signatory-caption">(Signature Over Printed Name)</div>
        <div class="signatory-field-label">Date:</div>
        <div class="signatory-field-line"></div>
        <div class="signatory-field-label">Time:</div>
        <div class="signatory-field-line"></div>
        <div class="signatory-field-label">Designation:</div>
        <div class="signatory-field-line"></div>
    </div>
@endif
