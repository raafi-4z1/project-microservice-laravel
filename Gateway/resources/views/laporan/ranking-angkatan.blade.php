<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Peringkat Se-Angkatan</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body   { font-size: 10px; color: #111; margin: 0; }
        h1     { font-size: 15px; margin: 0 0 2px; }
        .sub   { font-size: 10px; color: #555; margin-bottom: 10px; }
        .meta  { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .meta td { padding: 2px 0; font-size: 10px; }
        .meta .k { color: #555; width: 120px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #999; padding: 4px 5px; }
        table.data th { background: #eee; font-size: 9px; text-align: left; }
        td.num { text-align: right; }
        td.mid { text-align: center; }
        .warn  { color: #a00; }
        tfoot td { font-size: 9px; color: #555; border: none; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Laporan Peringkat Se-Angkatan</h1>
    <div class="sub">
        Tingkat {{ $tingkat }}
        @if($d['jurusan']) &middot; Jurusan {{ $d['jurusan'] }} @else &middot; semua jurusan @endif
    </div>

    <table class="meta">
        <tr>
            <td class="k">Tahun Ajaran</td><td>{{ $d['tahunAjaran'] }}</td>
            <td class="k">Rata-rata angkatan</td><td><strong>{{ $d['rataRataAngkatan'] }}</strong></td>
        </tr>
        <tr>
            <td class="k">Semester</td><td>{{ $d['semester'] }}</td>
            <td class="k">Jumlah siswa</td><td>{{ $d['totalSiswa'] }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:38px">Prk</th>
                <th style="width:95px">NISN</th>
                <th>Nama</th>
                <th style="width:80px">Kelas</th>
                <th style="width:55px">Rata-rata</th>
                <th style="width:45px">Predikat</th>
                <th style="width:70px">Blm dinilai</th>
            </tr>
        </thead>
        <tbody>
        @forelse($d['ranking'] as $r)
            <tr>
                <td class="mid">{{ $r['peringkat'] }}</td>
                <td>{{ $r['nisn'] }}</td>
                <td>{{ $r['namaLengkap'] }}</td>
                <td>{{ $r['kelas'] }}</td>
                <td class="num">{{ $r['rataRata'] }}</td>
                <td class="mid">{{ $r['predikat'] }}</td>
                <td class="mid {{ ($r['belumDinilai'] ?? 0) > 0 ? 'warn' : '' }}">
                    {{ $r['belumDinilai'] ?? 0 }} / {{ $r['jumlahMapel'] ?? 0 }}
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="mid">Belum ada siswa aktif pada angkatan ini.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="7">
                Peringkat sama berarti rata-rata sama (ditampilkan urut alfabet); peringkat
                berikutnya melompat. Mata pelajaran yang belum dinilai dihitung 0 —
                perhatikan kolom &ldquo;Blm dinilai&rdquo;. Siswa non-aktif tidak disertakan.
                Predikat: A &ge;90, B &ge;80, C &ge;70, D &ge;60, E &lt;60.
            </td></tr>
        </tfoot>
    </table>
</body>
</html>
