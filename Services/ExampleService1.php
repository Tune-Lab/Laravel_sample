<?php

class ExampleService1
{
    protected LogService $logService;
    protected NotificationService $notificationService;


    public function __construct()
    {
        $this->logService = new LogService();
        $this->notificationService = new NotificationService();
    }


    public function getBankAccounts(User $user): AnonymousResourceCollection
    {
        return BankAccountResource::collection(BankAccount::whereUserId($user->id)->orderByDesc('created_at')->get());
    }


    public function getAllBankAccounts(int $perPage): AnonymousResourceCollection
    {
        return BankAccountResource::collection(BankAccount::orderByDesc('created_at')->with('user')->paginate($perPage));
    }


    public function create(User $user, array $data): BankAccount
    {
        /* @var BankAccount $bankAccount */
        $bankAccount = $user->bankAccounts()->create($data);

        $data = $this->setTypeAccount($data);

        Pusher::eventNotificationAdmin($bankAccount);

        $logData = new LogData(
            LogActionTypeEnum::ACTION_NEW_BANK_ACCOUNT_MERCHANT,
            LogData::setEmptyData($data),
            $data,
        );

        $this->logService->create($logData);

        return $bankAccount;
    }


    public function update(BankAccount $bankAccount, array $data): BankAccount
    {
        $keys = $this->setTypeAccount($data);

        $logData = new LogData(
            LogActionTypeEnum::ACTION_CHANGE_BANK_ACCOUNT_MERCHANT,
            Arr::only($this->setTypeAccount($bankAccount->toArray()), array_keys($keys)),
            $keys
        );

        $this->logService->create($logData);

        $bankAccount->update($data);

        return $bankAccount;
    }


    public function destroyBankAccount(BankAccount $bankAccount): void
    {
        $logData = new LogData(LogActionTypeEnum::ACTION_DELETED_BANK_ACCOUNT_MERCHANT, null, null);

        $this->logService->create($logData);

        $bankAccount->delete();
    }


    public function updateState(BankAccount $bankAccount, int $status): void
    {
        $bankAccount->update(['status' => $status]);

        $text = "Bank Account $bankAccount->bank_id is ";
        $text .= $bankAccount->status == BankAccountStatusEnum::Approved()->value ? 'Approved' : 'Rejected';

        Pusher::eventNotificationMerchant($bankAccount, $bankAccount->status == BankAccountStatusEnum::Approved()->value ? 'success' : 'danger', $text);
    }


    private function setTypeAccount(array $data): array
    {
        if (Arr::exists($data, 'type_account')) {
            $data['type_account'] = BankAccountTypeEnum::getType($data['type_account']);
        }

        return $data;
    }
}
