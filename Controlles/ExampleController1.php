<?php

namespace Controlles;


class ExampleController1 extends Controller
{
    use AuthorizesRequests;


    public function __construct(
        protected LogService          $logService,
        protected BankAccountService  $bankAccountService,
        protected NotificationService $notificationService,
    )
    {
        parent::__construct();

        $this->authorizeResource(BankAccount::class, 'bankAccount');
    }


    public function index(): Response
    {
        $bankAccounts = $this->bankAccountService->getAllBankAccounts(self::PER_PAGE);

        return Inertia::render('BankAccount/BankAccountsAdminPage', [
            'bankAccounts' => $bankAccounts,
            'actions' => BankAccountStatusEnum::getStatuses()
        ]);
    }


    public function update(BankAccount $bankAccount, Request $request)
    {
        try {
            $status = $request->get('status');

            $this->bankAccountService->updateState($bankAccount, $status);

            return redirect()->route('admin-bank-account.index')->with('success', __($status == BankAccountStatusEnum::Approved()->value ? 'success.approved_bank_account' : 'success.rejected_bank_account'));
        } catch (\Exception $e) {
            Logging::createErrorLog("ExampleController1(approvedOrDisapproved) - " . $e->getMessage());

            return back()->with('error', $e->getMessage());
        }
    }
}
