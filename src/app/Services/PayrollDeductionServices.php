<?php

/**
 * Developer: Rainier Barbacena
 * Date: May 21, 2023
 * Description: This class handles the posting of Payroll Deduction Payments in the Laravel application.
 * It retrieves payment files from a shared folder, processes the files, and moves them to a backup location.
 * Only XLSX or XLS files are allowed for processing.
 */

namespace App\Services;

use Carbon\Carbon;
use App\Imports\PayrollDeductionErrorImport;
use App\Imports\PayrollDeductionImport;
use App\Models\RfidSerials;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Exception\RuntimeException;

class PayrollDeductionServices
{
    private $ip;
    private $payrollDeductionAccounts;
    private $payrollDeductionBackup;
    public function __construct()
    {
        $this->ip = env('SHARED_FOLDER_IP');
        $this->payrollDeductionAccounts = env('SHARED_FOLDER_PAYROLL_DEDUCTION_ACCOUNTS');
        $this->payrollDeductionBackup = env('SHARED_FOLDER_PAYROLL_DEDUCTION_BACKUP');
    }

    public function payment()
    {
        try {
            $source = $this->ip . $this->payrollDeductionAccounts;
            $destination = $this->ip . $this->payrollDeductionBackup;

            $files = File::files($source);
            
            echo "********************************************************* \n 
            Posting Payroll Deduction Payment \n \n";
            
            if (!empty($files)) {
                foreach ($files as $file) {
                    $extension = strtolower($file->getExtension());
                    if ($extension === 'xlsx' || $extension === 'xls') {
                        (new PayrollDeductionImport)->import($file->getPathname());
                    } else {
                        $output = new ConsoleOutput();
                        $errorMessage = " Invalid file format! \n Only XLSX or XLS files are allowed.";
                        return $output->writeln($errorMessage);
                    }
                }
            } else {
                $output = new ConsoleOutput();
                return $output->writeln(" File not found!\n Make sure to save the Payroll Deduction File in the directory folder. \n Path: " . $source);
            }
            
            if (count(glob($source . "/*")) !== 0) {
                File::copyDirectory($source, $destination);
                File::cleanDirectory($source);
            }
            
            echo "\n \n              Payment successfully posted! 
            \n ********************************************************";

            $this->clearTempStorage();
        } catch (\Exception $e) {
            $this->clearTempStorage();
            return response()->json(['message' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Payment successfully posted']);
    }

    public function clearTempStorage()
    {
        $path = storage_path('framework/laravel-excel');

        if (count(glob($path . "/*")) !== 0) {
            File::cleanDirectory($path);
        }
    }
}
