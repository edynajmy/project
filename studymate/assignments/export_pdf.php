<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    exit();
}

include("../config/database.php");
require("../fpdf/fpdf.php");

$user_id = $_SESSION['user_id'];

$result = mysqli_query(
    $conn,
    "SELECT *
     FROM assignments
     WHERE user_id='$user_id'
     ORDER BY due_date ASC"
);

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(190,10,'StudyMate Assignment Report',0,1,'C');

$pdf->Ln(5);

$pdf->SetFont('Arial','',12);

$pdf->Cell(60,10,'Title',1);
$pdf->Cell(40,10,'Subject',1);
$pdf->Cell(40,10,'Due Date',1);
$pdf->Cell(50,10,'Status',1);
$pdf->Ln();

while($row = mysqli_fetch_assoc($result))
{
    $pdf->Cell(60,10,$row['title'],1);
    $pdf->Cell(40,10,$row['subject'],1);
    $pdf->Cell(
        40,
        10,
        date("d M Y", strtotime($row['due_date'])),
        1
    );
    $pdf->Cell(50,10,$row['status'],1);
    $pdf->Ln();
}

$pdf->Output('D','Assignment_Report.pdf');
?>