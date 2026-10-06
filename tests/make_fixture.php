<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/bootstrap.php';
if (getenv('PK_TEST_DB')!=='1') throw new RuntimeException('Test fixture generation requires PK_TEST_DB=1.');
$pdf=new FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica','',12); $pdf->Cell(100,10,'PK document-control test fixture'); $pdf->Output('F',__DIR__.'/fixture.pdf');
