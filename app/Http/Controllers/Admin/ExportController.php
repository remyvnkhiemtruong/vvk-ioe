<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\ExamScore;
use App\Models\Incident;
use App\Models\ProctorAssignment;
use App\Models\SeatAssignment;
use App\Services\ExamSessionAvailabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;

class ExportController extends Controller
{
    public function registrations()
    {
        $rows = ExamRegistration::with(['exam', 'chosenSession', 'seatAssignment.session'])
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get()
            ->map(fn ($item) => [
                $item->full_name,
                $this->gradeFromClass($item->class_name),
                $item->class_name,
                $item->ioe_id,
                $this->identity($item),
                $item->phone,
                $item->email,
                $item->chosenSession?->name ?? $item->seatAssignment?->session?->name,
                $item->chosenSession?->targetLabel() ?? $item->seatAssignment?->session?->targetLabel(),
                $this->sessionWarning($item),
                $item->status,
                $item->uses_personal_computer ? 'Có' : 'Không',
            ])->all();

        return Excel::download(new ArrayExport(['Họ tên', 'Khối', 'Lớp', 'ID IOE', 'CCCD/Mã định danh', 'Điện thoại', 'Email', 'Ca thi', 'Khối/lớp áp dụng của ca', 'Ghi chú kiểm tra', 'Trạng thái', 'Máy cá nhân'], $rows), 'danh-sach-dang-ky-ioe.xlsx');
    }

    public function rooms()
    {
        $rows = ExamRoom::with('computers')->orderBy('room_name')->get()
            ->map(fn ($room) => [$room->room_code, $room->room_name, $room->location, $room->usable_computers, $room->backup_computers, $room->computers->where('status', 'broken')->count(), $room->status])
            ->all();

        return Excel::download(new ArrayExport(['Mã phòng', 'Tên phòng', 'Vị trí', 'Máy chính', 'Máy dự phòng', 'Máy hỏng', 'Trạng thái'], $rows), 'danh-sach-phong-thi.xlsx');
    }

    public function checkins()
    {
        return Excel::download(new ArrayExport($this->checkinHeadings(), $this->checkinRows()), 'danh-sach-check-in.xlsx');
    }

    public function checkinsPdf()
    {
        return $this->tablePdf('Danh sách check-in IOE', $this->checkinHeadings(), $this->checkinRows(), 'danh-sach-check-in.pdf');
    }

    public function scores()
    {
        return Excel::download(new ArrayExport($this->scoreHeadings(), $this->scoreRows()), 'bang-diem-ioe.xlsx');
    }

    public function scoresPdf()
    {
        return $this->tablePdf('Bảng điểm IOE cấp trường', $this->scoreHeadings(), $this->scoreRows(), 'bang-diem-ioe.pdf');
    }

    public function byod()
    {
        return Excel::download(new ArrayExport($this->byodHeadings(), $this->byodRows()), 'hoc-sinh-dung-may-ca-nhan.xlsx');
    }

    public function byodPdf()
    {
        return $this->tablePdf('Danh sách học sinh dùng máy cá nhân', $this->byodHeadings(), $this->byodRows(), 'hoc-sinh-dung-may-ca-nhan.pdf');
    }

    public function absent()
    {
        return Excel::download(new ArrayExport($this->absentHeadings(), $this->absentRows()), 'hoc-sinh-vang-thi.xlsx');
    }

    public function absentPdf()
    {
        return $this->tablePdf('Danh sách học sinh vắng thi', $this->absentHeadings(), $this->absentRows(), 'hoc-sinh-vang-thi.pdf');
    }

    public function technicalIncidents()
    {
        return Excel::download(new ArrayExport($this->incidentHeadings(), $this->technicalIncidentRows()), 'su-co-ky-thuat-chuyen-may.xlsx');
    }

    public function technicalIncidentsPdf()
    {
        return $this->tablePdf('Danh sách chuyển máy/sự cố kỹ thuật', $this->incidentHeadings(), $this->technicalIncidentRows(), 'su-co-ky-thuat-chuyen-may.pdf');
    }

    public function assignmentsPdf()
    {
        $assignments = SeatAssignment::with(['registration.chosenSession', 'session', 'room', 'computer'])
            ->orderBy('exam_session_id')
            ->orderBy('candidate_number')
            ->get();

        return Pdf::loadView('exports.assignments', compact('assignments'))->download('danh-sach-phong-thi.pdf');
    }

    public function proctors()
    {
        $rows = ProctorAssignment::with(['user', 'session.exam', 'room'])
            ->join('exam_sessions', 'proctor_assignments.exam_session_id', '=', 'exam_sessions.id')
            ->orderBy('exam_sessions.exam_date')
            ->orderBy('exam_sessions.start_time')
            ->orderBy('proctor_assignments.exam_room_id')
            ->select('proctor_assignments.*')
            ->get()
            ->map(fn ($assignment) => [
                $assignment->user?->name,
                $assignment->user?->email,
                $assignment->session?->exam?->name,
                $assignment->session?->name,
                $assignment->session?->exam_date?->format('d/m/Y'),
                $assignment->session?->start_time,
                $assignment->session?->end_time,
                $assignment->room?->room_name,
                $assignment->role,
                $assignment->note,
            ])
            ->all();

        return Excel::download(new ArrayExport([
            'Giám thị',
            'Email',
            'Kỳ thi',
            'Ca thi',
            'Ngày thi',
            'Giờ bắt đầu',
            'Giờ kết thúc',
            'Phòng thi',
            'Vai trò',
            'Ghi chú',
        ], $rows), 'phan-cong-giam-thi.xlsx');
    }

    public function incidentsDocx()
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addTitle('Biên bản sự cố IOE cấp trường', 1);

        foreach (Incident::with(['registration', 'assignment.session', 'assignment.room'])->latest()->get() as $incident) {
            $section->addText(($incident->reported_at?->format('d/m/Y H:i') ?? '').' - '.$incident->incident_type);
            $section->addText('Học sinh: '.($incident->registration?->full_name ?? 'Không rõ'));
            $section->addText('Ca/phòng: '.($incident->assignment?->session?->name ?? '-').' / '.($incident->assignment?->room?->room_name ?? '-'));
            $section->addText('Mô tả: '.$incident->description);
            $section->addText('Cách xử lý: '.($incident->solution ?? ''));
            $section->addTextBreak();
        }

        $path = tempnam(sys_get_temp_dir(), 'ioe-incident-').'.docx';
        (new Word2007($word))->save($path);

        return response()->download($path, 'bien-ban-su-co-ioe.docx')->deleteFileAfterSend(true);
    }

    private function identity(ExamRegistration $registration): string
    {
        return request()->user()->can('students.view_sensitive')
            ? $registration->identity_number
            : $registration->maskedIdentity();
    }

    private function checkinHeadings(): array
    {
        return ['Họ tên', 'Khối', 'Lớp', 'Ca thi', 'Khối/lớp áp dụng của ca', 'Phòng', 'Máy', 'Trạng thái', 'Ghi chú'];
    }

    private function checkinRows(): array
    {
        return SeatAssignment::with(['registration', 'session', 'room', 'computer', 'checkin'])->get()
            ->map(fn ($assignment) => [
                $assignment->registration->full_name,
                $this->gradeFromClass($assignment->registration->class_name),
                $assignment->registration->class_name,
                $assignment->session->name,
                $assignment->session->targetLabel(),
                $assignment->room->room_name,
                $assignment->seat_type === 'personal_computer' ? 'Máy cá nhân/BYOD' : $assignment->computer?->computer_label,
                $assignment->checkin?->status ?? 'not_checked_in',
                $assignment->checkin?->note,
            ])->all();
    }

    private function scoreHeadings(): array
    {
        return ['Họ tên', 'Khối', 'Lớp', 'ID IOE', 'Ca thi', 'Khối/lớp áp dụng của ca', 'Phòng', 'Điểm', 'Thời gian hoàn thành', 'Số câu đúng', 'Trạng thái điểm'];
    }

    private function scoreRows(): array
    {
        return ExamScore::with(['registration.chosenSession', 'registration.seatAssignment.session', 'registration.seatAssignment.room'])
            ->join('exam_registrations', 'exam_scores.exam_registration_id', '=', 'exam_registrations.id')
            ->orderByDesc('official_score')
            ->orderBy('completion_time_seconds')
            ->select('exam_scores.*')
            ->get()
            ->map(fn ($score) => [
                $score->registration->full_name,
                $this->gradeFromClass($score->registration->class_name),
                $score->registration->class_name,
                $score->registration->ioe_id,
                $score->registration->chosenSession?->name ?? $score->registration->seatAssignment?->session?->name,
                $score->registration->chosenSession?->targetLabel() ?? $score->registration->seatAssignment?->session?->targetLabel(),
                $score->registration->seatAssignment?->room?->room_name,
                $score->official_score,
                $score->completion_time_seconds,
                $score->correct_answers,
                $score->score_status,
            ])->all();
    }

    private function byodHeadings(): array
    {
        return ['Họ tên', 'Khối', 'Lớp', 'ID IOE', 'Ca thi', 'Thiết bị', 'Hệ điều hành', 'Có sạc', 'Trạng thái duyệt', 'Ghi chú'];
    }

    private function byodRows(): array
    {
        return ExamRegistration::with('chosenSession')
            ->where('uses_personal_computer', true)
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get()
            ->map(fn ($registration) => [
                $registration->full_name,
                $this->gradeFromClass($registration->class_name),
                $registration->class_name,
                $registration->ioe_id,
                $registration->chosenSession?->name,
                $registration->device_type,
                $registration->device_os,
                $registration->has_charger ? 'Có' : 'Không',
                $registration->personal_computer_status,
                $registration->device_note,
            ])->all();
    }

    private function absentHeadings(): array
    {
        return ['Họ tên', 'Khối', 'Lớp', 'Ca thi', 'Phòng', 'Máy', 'Ghi chú'];
    }

    private function absentRows(): array
    {
        return Checkin::with(['assignment.registration', 'assignment.session', 'assignment.room', 'assignment.computer'])
            ->where('status', 'absent')
            ->get()
            ->map(fn ($checkin) => [
                $checkin->assignment->registration->full_name,
                $this->gradeFromClass($checkin->assignment->registration->class_name),
                $checkin->assignment->registration->class_name,
                $checkin->assignment->session->name,
                $checkin->assignment->room->room_name,
                $checkin->assignment->computer?->computer_label,
                $checkin->note,
            ])->all();
    }

    private function incidentHeadings(): array
    {
        return ['Thời gian', 'Học sinh', 'Lớp', 'Ca thi', 'Phòng', 'Loại sự cố', 'Mô tả', 'Cách xử lý'];
    }

    private function technicalIncidentRows(): array
    {
        $types = ['Máy tính lỗi', 'Mất mạng', 'Chuyển máy', 'Máy cá nhân không hoạt động', 'Lỗi trình duyệt'];

        return Incident::with(['registration', 'assignment.session', 'assignment.room'])
            ->whereIn('incident_type', $types)
            ->latest()
            ->get()
            ->map(fn ($incident) => [
                $incident->reported_at?->format('d/m/Y H:i'),
                $incident->registration?->full_name,
                $incident->registration?->class_name,
                $incident->assignment?->session?->name,
                $incident->assignment?->room?->room_name,
                $incident->incident_type,
                $incident->description,
                $incident->solution,
            ])->all();
    }

    private function tablePdf(string $title, array $headings, array $rows, string $fileName)
    {
        return Pdf::loadView('exports.table', compact('title', 'headings', 'rows'))->download($fileName);
    }

    private function gradeFromClass(?string $className): ?int
    {
        return preg_match('/^(10|11|12)/', (string) $className, $matches) ? (int) $matches[1] : null;
    }

    private function sessionWarning(ExamRegistration $registration): string
    {
        if (! $registration->chosenSession) {
            return 'Chưa chọn ca thi.';
        }

        $availability = app(ExamSessionAvailabilityService::class);

        return $availability->isExamTargetForStudent($registration->exam, $registration)
            && $availability->isTargetForStudent($registration->chosenSession, $registration)
            ? ''
            : 'Ca thi không phù hợp khối/lớp.';
    }
}
