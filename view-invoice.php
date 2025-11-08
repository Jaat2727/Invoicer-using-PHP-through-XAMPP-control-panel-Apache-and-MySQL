<?php
// --- 1. Start Session & Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("You must be logged in to view invoices.");
}
$user_id = $_SESSION['user_id'];

// --- 2. Include the FPDF Library ---
require('fpdf.php');

// --- 3. Get Invoice ID from URL ---
if (!isset($_GET['id'])) {
    die("Invalid request: No invoice ID.");
}
$invoice_id = (int)$_GET['id'];

// --- 4. Database Connection ---
require_once 'db.php';

// --- 5. Fetch ALL Data for the Invoice ---

// A. Get My Company Settings
$sql_settings = "SELECT * FROM settings WHERE user_id = ?";
$stmt_settings = $conn->prepare($sql_settings);
$stmt_settings->bind_param("i", $user_id);
$stmt_settings->execute();
$my_company = $stmt_settings->get_result()->fetch_assoc();
$stmt_settings->close();
if (!$my_company) {
    die("Error: Your company settings are not set up. Please go to the Settings page.");
}

// B. Get Invoice Details & Customer Details (2-in-1 query)
$sql_invoice = "SELECT i.*, c.company_name, c.address, c.gstin, c.state, c.state_code
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                WHERE i.id = ? AND i.user_id = ?";
$stmt_invoice = $conn->prepare($sql_invoice);
$stmt_invoice->bind_param("ii", $invoice_id, $user_id);
$stmt_invoice->execute();
$invoice = $stmt_invoice->get_result()->fetch_assoc();
$stmt_invoice->close();
if (!$invoice) {
    die("Error: Invoice not found or you do not have permission to view it.");
}

// C. Get Invoice Items (many items)
$sql_items = "SELECT ii.*, p.product_name
              FROM invoice_items ii
              JOIN products p ON ii.product_id = p.id
              WHERE ii.invoice_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

$conn->close();

// --- 6. Create the PDF ---

class PDF extends FPDF {
    var $my_company;
    var $invoice_tagline;

    // Brand colors
    var $primary_color = array(33, 150, 243);      // Modern Blue
    var $secondary_color = array(13, 71, 161);     // Dark Blue
    var $accent_color = array(255, 152, 0);        // Orange
    var $light_bg = array(245, 248, 250);          // Light blue-gray
    var $table_header = array(25, 118, 210);       // Rich blue
    var $table_alt = array(250, 250, 252);         // Very light gray

    // Rounded rectangle function
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    // Enhanced Header
    function Header() {
        // Top colored band
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Rect(0, 0, 210, 8, 'F');

        $this->Ln(12);

        // Company section with background box
        $this->SetFillColor($this->light_bg[0], $this->light_bg[1], $this->light_bg[2]);
        $this->RoundedRect(10, $this->GetY(), 120, 35, 3, 'F');

        $this->SetXY(15, $this->GetY() + 3);

        // Company Name in brand color
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->Cell(110, 7, strtoupper($this->my_company['company_name']), 0, 1, 'L');

        $this->SetX(15);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(70, 70, 70);

        // Address with icon simulation
        $address = $this->my_company['address'] ?? 'N/A';
        $x = 15;
        $y = $this->GetY();
        $this->SetXY($x, $y);
        $this->MultiCell(110, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $address), 0, 'L');

        $this->SetX(15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(110, 4, "GSTIN: " . $this->my_company['gstin'], 0, 1, 'L');
        $this->SetX(15);
        $this->Cell(110, 4, "Mobile: " . $this->my_company['mobile'], 0, 1, 'L');

        // --- INVOICE Title Box ---
        $this->SetY(20);
        $this->SetX(140);

        // Invoice badge
        $this->SetFillColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->RoundedRect(135, 20, 65, 30, 3, 'F');

        $this->SetXY(140, 22);
        $this->SetFont('Arial', 'B', 26);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(55, 10, 'INVOICE', 0, 1, 'C');

        $this->SetXY(140, 32);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(220, 220, 220);
        $this->Cell(55, 5, 'Tax Invoice', 0, 1, 'C');

        $this->Ln(20);
    }

    // Enhanced Footer
    function Footer() {
        $this->SetY(-25);

        // Decorative line with gradient effect
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.8);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        $this->Ln(3);

        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);

        // Tagline on left
        $this->Cell(95, 4, $this->invoice_tagline, 0, 0, 'L');

        // Page number in center
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(95, 4, 'Page ' . $this->PageNo(), 0, 0, 'R');

        $this->Ln(5);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 3, 'This is a computer-generated invoice and does not require a physical signature.', 0, 0, 'C');
    }

    // Enhanced Table with modern styling
    function ItemTable($header, $data) {
        // Table header with gradient-like effect
        $this->SetFillColor($this->table_header[0], $this->table_header[1], $this->table_header[2]);
        $this->SetDrawColor(200, 200, 200);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->SetLineWidth(0.3);

        // Column widths
        $w = array(15, 85, 20, 35, 35);

        // Header with rounded top
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        // Data rows with alternating colors
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        $fill = false;
        $s_no = 1;
        $subtotal = 0;

        foreach($data as $row) {
            $item_total = $row['quantity'] * $row['price_per_unit'];
            $subtotal += $item_total;

            // Alternate row colors
            if($fill) {
                $this->SetFillColor($this->table_alt[0], $this->table_alt[1], $this->table_alt[2]);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->Cell($w[0], 7, $s_no++, 'LR', 0, 'C', true);
            $this->Cell($w[1], 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $row['product_name']), 'LR', 0, 'L', true);
            $this->Cell($w[2], 7, $row['quantity'], 'LR', 0, 'C', true);
            $this->Cell($w[3], 7, number_format($row['price_per_unit'], 2), 'LR', 0, 'R', true);
            $this->Cell($w[4], 7, number_format($item_total, 2), 'LR', 1, 'R', true);

            $fill = !$fill;
        }

        // Table bottom border
        $this->Cell(array_sum($w), 0, '', 'T');
        return $subtotal;
    }
}

// --- 7. Instantiate and Build PDF Document ---
$pdf = new PDF('P', 'mm', 'A4');
$pdf->my_company = $my_company;
$pdf->invoice_tagline = $my_company['tagline'] ?? 'Thank you for your business!';

$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// --- Bill To & Invoice Details in Styled Boxes ---
$y_start = $pdf->GetY();

// Bill To Box
$pdf->SetFillColor(245, 248, 250);
$pdf->RoundedRect(10, $y_start, 95, 40, 3, 'F');
$pdf->SetDrawColor(200, 200, 200);
$pdf->RoundedRect(10, $y_start, 95, 40, 3, 'D');

$pdf->SetXY(12, $y_start + 2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(33, 150, 243);
$pdf->Cell(90, 6, 'BILL TO', 0, 1, 'L');

$pdf->SetX(12);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(90, 5, $invoice['company_name'], 0, 1, 'L');

$pdf->SetX(12);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(70, 70, 70);
$address = $invoice['address'] ?? 'N/A';
$pdf->MultiCell(90, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $address), 0, 'L');

$pdf->SetX(12);
$pdf->Cell(90, 4, "State: " . $invoice['state'] . " | Code: " . $invoice['state_code'], 0, 1, 'L');
$pdf->SetX(12);
$pdf->Cell(90, 4, "GSTIN: " . $invoice['gstin'], 0, 1, 'L');

// Invoice Details Box
$pdf->SetFillColor(255, 255, 255);
$pdf->RoundedRect(110, $y_start, 90, 40, 3, 'F');
$pdf->SetDrawColor(33, 150, 243);
$pdf->SetLineWidth(0.5);
$pdf->RoundedRect(110, $y_start, 90, 40, 3, 'D');

$pdf->SetXY(112, $y_start + 2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(33, 150, 243);
$pdf->Cell(86, 6, 'INVOICE DETAILS', 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(70, 70, 70);

// Invoice number
$pdf->SetXY(112, $pdf->GetY());
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 5, 'Invoice No.', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(46, 5, $invoice['invoice_number'], 0, 1, 'R');

// Date
$pdf->SetX(112);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 5, 'Date', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(46, 5, date("d-M-Y", strtotime($invoice['invoice_date'])), 0, 1, 'R');

// Vehicle
$pdf->SetX(112);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 5, 'Vehicle No.', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(46, 5, $invoice['vehicle_number'], 0, 1, 'R');

// State of supply
$pdf->SetX(112);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 5, 'State of Supply', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(46, 5, $invoice['state_of_supply'], 0, 1, 'R');

// --- 8. Items Table ---
$pdf->Ln(12);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 150, 243);
$pdf->Cell(0, 6, 'ITEMS & DESCRIPTION', 0, 1, 'L');
$pdf->Ln(2);

$table_header = array('S.No', 'Item Description', 'Qty', 'Rate (Rs.)', 'Total (Rs.)');
$subtotal = $pdf->ItemTable($table_header, $items);

// --- 9. Totals Section with Enhanced Styling ---
$pdf->Ln(8);

// Totals box
$totals_y = $pdf->GetY();
$pdf->SetFillColor(245, 248, 250);
$pdf->RoundedRect(110, $totals_y, 90, 45, 3, 'F');

$pdf->SetXY(115, $totals_y + 3);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(70, 70, 70);

// Subtotal
$pdf->Cell(40, 6, 'Subtotal', 0, 0, 'L');
$pdf->Cell(40, 6, 'Rs. ' . number_format($subtotal, 2), 0, 1, 'R');

// GST
$pdf->SetX(115);
if ((float)$invoice['igst_amount'] > 0) {
    $pdf->Cell(40, 6, 'IGST', 0, 0, 'L');
    $pdf->Cell(40, 6, 'Rs. ' . number_format($invoice['igst_amount'], 2), 0, 1, 'R');
} else {
    $pdf->Cell(40, 6, 'CGST', 0, 0, 'L');
    $pdf->Cell(40, 6, 'Rs. ' . number_format($invoice['cgst_amount'], 2), 0, 1, 'R');

    $pdf->SetX(115);
    $pdf->Cell(40, 6, 'SGST', 0, 0, 'L');
    $pdf->Cell(40, 6, 'Rs. ' . number_format($invoice['sgst_amount'], 2), 0, 1, 'R');
}

// Divider line
$pdf->SetX(115);
$pdf->SetDrawColor(33, 150, 243);
$pdf->SetLineWidth(0.3);
$pdf->Cell(80, 3, '', 'T', 1, 'L');

// Grand Total - Highlighted
$pdf->SetX(110);
$pdf->SetFillColor(33, 150, 243);
$pdf->RoundedRect(110, $pdf->GetY(), 90, 10, 3, 'F');

$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(40, 10, 'GRAND TOTAL', 0, 0, 'L');
$pdf->Cell(40, 10, 'Rs. ' . number_format($invoice['total_amount'], 2), 0, 1, 'R');

// --- 10. Payment & Signature ---
$pdf->Ln(15);

// Payment details box
$pdf->SetFillColor(255, 249, 240);
$pdf->RoundedRect(10, $pdf->GetY(), 95, 15, 3, 'F');
$pdf->SetDrawColor(255, 152, 0);
$pdf->RoundedRect(10, $pdf->GetY(), 95, 15, 3, 'D');

$pdf->SetXY(12, $pdf->GetY() + 2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(255, 152, 0);
$pdf->Cell(90, 4, 'PAYMENT DETAILS', 0, 1, 'L');
$pdf->SetX(12);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(70, 70, 70);
$pdf->Cell(90, 5, 'UPI ID: ' . $my_company['upi_id'], 0, 1, 'L');

// Signature box
$pdf->SetY($pdf->GetY() - 15);
$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(80, 5, 'For ' . $my_company['company_name'], 0, 1, 'L');

$pdf->SetX(115);
$pdf->Ln(12);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetX(115);
$pdf->Cell(80, 5, '(Authorised Signatory)', 'T', 1, 'C');

$pdf_filename = "Invoice-" . $invoice['invoice_number'] . ".pdf";

// --- 11. Output the PDF ---
$pdf->Output('D', $pdf_filename);
exit();
?>
