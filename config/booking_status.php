<?php

function complete_ended_bookings(mysqli $conn): void
{
    $conn->query("
        UPDATE bookings
        SET status = 'completed'
        WHERE status = 'confirmed'
          AND end_datetime <= NOW()
    ");
}
