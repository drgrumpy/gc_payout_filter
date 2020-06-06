/*
 *  filter the payout items list by payment_id
 * 
 *  we use this to get the fees for each payment when a payment is paid_out
 *  
 *  Intended to be called within a webhook payment 'paid out' event handler. 
 *  Takes two parameters: the payout_id and the payment_id.
 *  Returns an array of data containing the date, gross amount and fee.
 *
 *  2019-02-04 Updated to set limit of 500 items in the list... 
 *  This will effectively handle ~250 payments for each payout.
 *  So if we have more than 200 or so DD payers we need to figure out a better
 *  way of doing this...
 * 
 */

function payout_filter($payout_id, $gc_pmnt_id) {

    global $client;
    global $logfile;

    $data = array(); // array to hold the output
    
    $payout = $client->payouts()->get($payout_id);
    
    //payout date
    $data['payout_date'] = $payout->created_at;
    
    try {
        $items = $client->payoutItems()->list([
            'params' => ['payout' => "$payout_id",
                         'limit' => '500']
        ]);
    } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
        $msg = $e->getMessage();
        // echo $msg;
        write_log('payout_filter ' . $msg, $logfile);
        return FALSE;
    }

    //$msgList[] = print_r($items->records, TRUE); // for testing
    // it seems this list is an object containing an array of objects !
    $records = $items->records;

    foreach ($records as $item) {
        if ($item->links->payment == $gc_pmnt_id) {

            if ($item->type == 'gocardless_fee') {
                //$gc_pmnt_id = $item->links->payment;
                // fee charged will be negative, otherwise it will be a chargeback...
                $data['gc_fee'] = $item->amount; // fees are in pence negative                
            } else if ($item->type == 'payment_paid_out') {
                $data['gc_gross'] = $item->amount;
            } else if ($item->type == 'payment_charged_back') {
                $data['gc_gross'] = $item->amount;
            }
        }
    }

    return $data;
}
