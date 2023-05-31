<?php

/**
 * Developer: Rainier Barbacena
 * Date: May 21, 2023
 * Description: This class handles the import of Payroll Deduction data in the Laravel application.
 * It implements the Laravel Excel import interfaces to process the data from a collection.
 * The imported data is used to apply payments to the appropriate RFID serials, checking for duplicates and generating discounts if applicable.
 * If any errors occur during the import process, they are captured and stored in the uploadErrors array.
 * The import progress is displayed using a console progress bar.
 * The database connection and various helper functions are utilized to ensure data integrity and accurate payment processing.
 */

namespace App\Imports;

use Carbon\Carbon;
use App\Models\Payments;
use App\Models\RfidLedger;
use App\Models\RfidSerials;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Exception\RuntimeException;


class PayrollDeductionImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    use Importable;

    public function iterate()
    {
        static $counter = 0;
        $counter++;
        return $counter;
    }
    
    public function collection(Collection $collection)
    {   
        $currentDateTime = Carbon::now();
        $transactionDate = $currentDateTime->format('Y-m-d H:i:s.v');
    
        $output = new ConsoleOutput();
        $progressBar = new ProgressBar($output, $collection->count());
        $progressBar->setBarCharacter('<fg=green>=</>');
        $progressBar->setProgressCharacter('<fg=green><3</>');
        $currentSheet = $this->iterate();
        if ($currentSheet == 1) {
            echo " Non-confidential \n";
        } elseif ($currentSheet == 2) {
            echo "\n Confidential \n";
        }
        $progressBar->start();

        // Check if the required headers exist
        $requiredHeaders = ['card_no', 'full_name', 'payroll_deduction', 'reference'];
        $missingHeaders = array_diff($requiredHeaders, $collection->first()->keys()->all());
        if (!empty($missingHeaders)) {
            $output = new ConsoleOutput();
            $errorMessage = "\n Incorrect or Missing header/s: " . implode(', ', $missingHeaders);
            $output->writeln($errorMessage . "\n Please fix and try again.");
        }

        $uploadErrors = [];

        $collection->each(function ($sheet) use ($progressBar, $transactionDate, &$uploadErrors) {
            
            try {

                // sheet column
                $cardno = $sheet['card_no'];
                $cardName = $sheet['full_name'];
                $payAmount = (float)$sheet['payroll_deduction'];
                $refNo = $sheet['reference'];
                $paymode = 'Payroll Deduction';
    
                // serials
                $serials = $this->getSerials($cardno, $cardName);
                
                if ($serials) {
                    $sno = $serials->Sno;
                    
                    $duplicate = $this->checkDuplicatePayment($sno, $refNo, $transactionDate, $payAmount);

                    if ($duplicate === 0) {
                        DB::connection('sqlsrv')->select(DB::raw(
                            "exec ApplyPaymentV2 :cardno, :transactionDate, :referenceNo, :payAmount, :discount, :posid, :cashreg, :paymode"
                        ), [
                            ':cardno' => $cardno,
                            ':transactionDate' => $transactionDate,
                            ':referenceNo' => $refNo,
                            ':payAmount' => $payAmount,
                            ':discount' => $this->discountGen($cardno, $payAmount, $transactionDate),
                            ':posid' => null,
                            ':cashreg' => null,
                            ':paymode' => $paymode,
                        ]);                
                        $progressBar->advance();
                    } else {
                        $uploadErrors[] = 'Error processing row: ' . $cardno . ' ' . $cardName . ' - Duplicate payment detected.';
                    }
                } else {
                    $uploadErrors[] = 'Error processing row: ' . $cardName . ' - Serials not found.';
                }

            } catch (\Exception $e) {
                // $uploadErrors[] = 'Error processing row: ' . $cardno . ' ' . $cardName . ' - ' . $e->getMessage();
            }
        });
    
        $progressBar->finish();
        $output->writeln(' ♥');
        
        // Display upload errors, if any
        if (!empty($uploadErrors)) {
            $output = new ConsoleOutput();
            $output->writeln("\n Upload Errors:");
            foreach ($uploadErrors as $error) {
                $output->writeln($error);
            }
        }
    }    

    public function discountGen($cardno, $payAmount, $transactionDate)
    {
        $discount =  collect(DB::connection('sqlsrv')->select(DB::raw(
            "exec ECPayBillDueDateDiscount :cardno, :date"
        ), [
            ':cardno' => $cardno,
            ':date' => $transactionDate,
        ]));

        $newDiscount = $discount->filter(function ($value) use ($payAmount) {
            return $value->Amount <= $payAmount;
        });

        return optional($newDiscount->pop())->Discount ?? 0;
    }

    private function getSerials($cardno, $cardName)
    {
        return RfidSerials::where('cardno', $cardno)
            ->orWhere('Cardname', $cardName)
            ->first();
    }

    public function checkDuplicatePayment($sno, $refNo, $transactionDate, $payAmount)
    {    
        $payment = Payments::where([
            ['Sno', '=', $sno],
            ['RefNo', '=', $refNo],
        ])
            ->where(DB::raw("convert(char(10),transactiondate,101)"), $transactionDate)
            ->first();
    
        $result = DB::connection('sqlsrv')->table('tblRFIDLedger')
            ->select('*')
            ->where([
                ['SerialNo', '=', $sno],
                ['RefNo', '=', $refNo],
                ['Remarks', 'like', '%Payment%'],
                ['GrossAmount', $payAmount + optional($payment)->Discounts ?? 0],
            ])
            ->where(DB::raw("convert(char(10),transactiondate,101)"), $transactionDate)
            ->get();
    
        return $result->count();
    }    

    public function headingRow(): int
    {
        return 7;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
