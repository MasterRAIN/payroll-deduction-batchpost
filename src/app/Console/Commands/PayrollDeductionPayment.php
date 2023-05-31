<?php

/**
 * Developer: Rainier Barbacena
 * Date: May 21, 2023
 * Description: This command handles the Payroll Deduction Payment Gateway in the Laravel application.
 * It checks the database connection, initiates the PayrollDeductionServices class, and executes the payment process.
 * If a database connection error occurs, an error message is displayed.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Imports\PayrollDeductionImport;
use App\Services\PayrollDeductionServices;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutput;

class PayrollDeductionPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payrolldeduction:pay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Payroll Deduction Payment Gateway';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            DB::connection('sqlsrv')->getPdo();
        } catch (\Exception $e) {
            $output = new ConsoleOutput();
            $errorMessage = "Database connection error: \n" . $e->getMessage();
            return $output->writeln($errorMessage);
        }
        $payrollPayment = new PayrollDeductionServices;
        $payrollPayment->payment();
        $payrollPayment->clearTempStorage();
    }
}
