<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}h1{text-align:center;font-size:18px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #333;padding:5px}th{background:#eee}</style></head><body>
<h1>DANH SÁCH PHÒNG THI IOE CẤP TRƯỜNG</h1>
<table><thead><tr><th>SBD</th><th>Họ tên</th><th>Khối</th><th>Lớp</th><th>Ca học sinh chọn</th><th>Ca phân phòng</th><th>Khối/lớp áp dụng</th><th>Phòng</th><th>Máy</th></tr></thead><tbody>
@foreach($assignments as $assignment)
    @php($grade = preg_match('/^(10|11|12)/', $assignment->registration->class_name, $matches) ? $matches[1] : '')
    <tr>
        <td>{{ $assignment->candidate_number }}</td>
        <td>{{ $assignment->registration->full_name }}</td>
        <td>{{ $grade }}</td>
        <td>{{ $assignment->registration->class_name }}</td>
        <td>{{ $assignment->registration->chosenSession?->name ?? 'Dữ liệu cũ chưa chọn ca' }}</td>
        <td>{{ $assignment->session->name }}</td>
        <td>{{ $assignment->session->targetLabel() }}</td>
        <td>{{ $assignment->room->room_name }}</td>
        <td>{{ $assignment->seat_type === 'personal_computer' ? 'Máy cá nhân/BYOD' : $assignment->computer?->computer_label }}</td>
    </tr>
@endforeach
</tbody></table>
</body></html>
