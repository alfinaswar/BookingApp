{{-- Partial: matriks unit × tanggal --}}
@php
    $dates = $calendar['dates'];
@endphp

@if (empty($calendar['types']))
    <div class="alert alert-warning">Belum ada unit aktif. Jalankan <code>php artisan db:seed --class=RoomUnitSeeder</code>.</div>
@else
    <table class="table table-sm table-bordered align-middle mb-0" style="font-size:12px;">
        <thead class="sticky-top bg-white">
            <tr>
                <th style="min-width:130px;position:sticky;left:0;background:#fff;z-index:2;">Unit</th>
                @foreach ($dates as $date)
                    @php $d = \Carbon\Carbon::parse($date); @endphp
                    <th class="text-center {{ in_array($d->dayOfWeekIso, [6, 7]) ? 'bg-red-lt' : '' }}"
                        style="min-width:44px;" data-date="{{ $date }}" role="button"
                        title="Pilih rentang mulai {{ $date }}">
                        {{ $d->format('d') }}<br>
                        <small class="text-muted">{{ $d->isoFormat('dd') }}</small>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($calendar['types'] as $type)
                <tr class="bg-azure-lt">
                    <td colspan="{{ count($dates) + 1 }}" style="position:sticky;left:0;">
                        <strong>{{ $type['tipe'] }}</strong>
                        <span class="text-muted">
                            · {{ count($type['units']) }} unit
                            · maks {{ $type['kapasitas_max'] }} org
                            · Rp {{ number_format($type['base_rate_weekday'], 0, ',', '.') }}/malam (weekday)
                        </span>
                    </td>
                </tr>
                @foreach ($type['units'] as $unit)
                    <tr data-unit-id="{{ $unit['unit_id'] }}" data-unit-kode="{{ $unit['kode'] }}"
                        data-unit-tipe="{{ $type['tipe'] }}" data-room-id="{{ $type['room_id'] }}">
                        <td style="position:sticky;left:0;background:#fff;z-index:1;">
                            <strong>{{ $unit['kode'] }}</strong>
                            <div class="text-muted" style="font-size:11px;">{{ $unit['nama'] }}</div>
                        </td>
                        @foreach ($dates as $date)
                            @php $cell = $unit['cells'][$date] ?? ['state' => 'available']; @endphp
                            <td class="text-center cell-{{ $cell['state'] }}" data-date="{{ $date }}"
                                data-state="{{ $cell['state'] }}"
                                @if ($cell['state'] === 'available') role="button" @endif
                                @if (! empty($cell['note'])) title="{{ $cell['note'] }}" @endif>
                                @if ($cell['state'] === 'available')
                                    <i class="fas fa-plus text-green" style="opacity:.35"></i>
                                @elseif ($cell['state'] === 'blocked')
                                    <i class="fas fa-lock text-muted"></i>
                                @else
                                    <i class="fas fa-times text-red"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@endif

<style>
    .cell-available { background: #e8f7ee; cursor: pointer; }
    .cell-available:hover { background: #b8e6c8; }
    .cell-available.selected { background: #2fb344 !important; }
    .cell-available.selected i { color: #fff !important; opacity: 1 !important; }
    .cell-available.sel-start { box-shadow: inset 3px 0 0 #1f573a; }
    .cell-available.sel-end { box-shadow: inset -3px 0 0 #1f573a; }
    .cell-booked { background: #ffe3e3; }
    .cell-blocked { background: #eceef0; }
    th[data-date] { cursor: pointer; user-select: none; }
    th[data-date]:hover { background: #bfe3ff; }
    th[data-date].in-range { background: #e3f0ff; }
    th[data-date].sel-start,
    th[data-date].sel-end { box-shadow: inset 0 -3px 0 #1f573a; }
</style>
