<?php

namespace App\Services;

/**
 * Tên nghiệp vụ mới cho service phân phòng. Giữ kế thừa SeatAssignmentService
 * để không phá các controller và dữ liệu seat_assignments hiện có.
 */
class RoomAssignmentService extends SeatAssignmentService {}
