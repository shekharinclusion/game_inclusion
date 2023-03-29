<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Admin;
use PayPal\Api\Item;
use App\Models\Order;
use App\Models\Price;
use PayPal\Api\Payer;
use App\Models\Coupon;
use PayPal\Api\Amount;
use App\Models\Spacing;
use App\Models\Subject;
use PayPal\Api\Payment;
use App\Models\Material;
use PayPal\Api\ItemList;
use PayPal\Api\Transaction;
use PayPal\Rest\ApiContext;
use Illuminate\Http\Request;
use PayPal\Api\RedirectUrls;
use Illuminate\Http\Response;
use App\Models\ReferencingStyle;
use PayPal\Api\PaymentExecution;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Jobs\OrderPlacedTagJob;
use App\Jobs\SendEmailJob;
use App\Jobs\UserSendEmail;
use App\Jobs\WriterSendJob;
use App\Models\AssignOrder;
use App\Models\MessageConfig;
use App\Models\OrderBid;
use App\Models\Refferal;
use App\Models\SiteConfig;
use App\Models\Wallet;
use App\Models\Writer;
use Hashids\Hashids;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PayPal\Auth\OAuthTokenCredential;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\HelperTraits;
use App\Models\OrderMaterial;
use App\Models\OrderMessage;
use App\Models\OrderRevision;
use App\Models\OrderHistory;
use App\Models\PendingEmail;
use Illuminate\Support\Facades\Http;
use App\Models\ConvertKitFunctions;
class OrderController extends Controller
{
    use AuthenticatesUsers, HelperTraits,ConvertKitFunctions;

    private $_api_context;

    public function __construct()
    {
        $payPalConfiguration = Config::get('paypal');
        $this->_api_context = new ApiContext(new OAuthTokenCredential($payPalConfiguration['client_id'], $payPalConfiguration['secret']));
        $this->_api_context->setConfig($payPalConfiguration['settings']);
    }

    /**
     * Show the application orderForm.
     *
     * @return \Illuminate\View\View
     */
    public function orderForm(Request $request)
    {
        return view('user.order.order-form', [
            'prices' => Price::get(),
            'subjects' => Subject::get(),
            'spacings' => Spacing::get(),
            'referencingStyles' => ReferencingStyle::get(),
            'typeofworks' => Price::pluck('typeofwork')->unique(),
            'writer' => Writer::select('id', 'first_name', 'last_name')->whereId($request->id)->first(),
            'request' => $request
        ]);
    }

    /**
     * Fetch the urgency for dropdown.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function fetchUrgency(Request $request)
    {
        $prices = Price::where("typeofwork", $request->typeofwork)->get();
        foreach ($prices as $price) {
            $row['value'] = $price->urgency;
            $row['name'] = $price->urgency . now()->add($price->urgency)->format(', l M d, Y');

            $data[] = $row;
        }

        return response()->json($data);
    }

    public function fetchPriceCalculate(Request $request)
    {
        $pages = $request->pages;
        $string = '';

        $data = Price::where("typeofwork", $request->typeofwork)->orderBy('urgency', 'asc')->get();
        $i = 1;
        $string = '';
        $stringbody = '';
        $stringhead = '';

        foreach ($data as $da) {
            $jsondata = $da['academy'];
            if ($i == 1) {
                $stringhead .= '<thead id="table-head"><tr ><th>Urgency</th>';
                $stringbody .= '<tbody id="table-body"><tr id="id-' . $i . '"><td id="id-' . $i . '"><a href="javascript:void(0)">' . $da['urgency'] . '</a></td>';
                foreach ($jsondata as $key => $value) {
                    if ($value != 0) {
                        $stringhead .= '<th  class="hover-price">' . str_replace('_', ' ', $key) . '</th>';
                        $stringbody .= '<td class="' . $key . '" onclick="myprice(' . number_format($value * $pages, 2, '.', '') . ')"><a href="javascript:void(0)" >' . number_format($value * $pages, 2, '.', '') . '</a></td>';
                    }
                }
                $stringbody .= '</tr>';
                $stringhead .= '</tr></thead>';
            } else {
                $setclass = "";
                if ($i == 2) {
                    $setclass = "setactive";
                }
                $stringbody .= '<tr id="id-' . $i . '">';
                $stringbody .= '<td id="id-' . $i . '"><a href="javascript:void(0)" >' . $da['urgency'] . '</a></td>';
                foreach ($jsondata as $key => $value) {
                    if ($value != 0) {
                        $stringbody .= '<td class="' . $key . '" onclick="myprice(' . number_format($value * $pages, 2, '.', '') . ')"><a href="javascript:void(0)" >' . number_format($value * $pages, 2, '.', '') . '</a></td>';
                    }
                }
                $stringbody .= '</tr>';
            }
            $i++;
        }
        $stringbody .= '</tbody>';
        $string = $stringhead . $stringbody;

        return response()->json($string);
    }


    /**
     * Fetch the academy for dropdown.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function fetchAcademy(Request $request)
    {
        $prices = Price::where("typeofwork", $request->typeofwork)->where("urgency", $request->urgency)->first();
        foreach ($prices->academy as $academy => $price) {
            if ($price != 0) {
                $row['name'] = $academy;
                $row['price'] = $price;
                $data[] = $row;
            }
        }
        return response()->json($data);
    }

    /**
     * check email.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function checkEmail(Request $request)
    {
        $email = User::where('email', $request->email)->first();

        if ($email) {

            return response()->json([
                'status' => true,
                'message' => 'The user email has already been taken.',
            ]);
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Email address is not registered',
            ]);
        }
    }

    /**
     * apply coupon code.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function applyCouponCode(Request $request)
    {
        $coupon = Coupon::where('code', $request->couponCode)->whereDate('start_date', '<=', date('Y-m-d'))->whereDate('end_date', '>=', date('Y-m-d'))->first();

        if ($coupon) {

            return response()->json([
                'status' => true,
                'message' => 'Coupon applied',
                'data' => $coupon
            ]);
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Coupon is not valid',
            ]);
        }
    }

    /**
     * process the order.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function orderProcess(Request $request)
    {

        if ($request->user_target == null) {
            if (isset($request->reffer_user_id)) {
                $request->validate([
                    'preffer_user' => 'required'
                ]);
            } else {
                $request->validate([
                    // 'preferredwriter' => 'required'
                ]);
            }
        } elseif ($request->user_target == 'register') {
            $request->validate([
                'user_email' => 'email|unique:users,email',
                'first_name' => ['required', 'string', 'max:10'],
                'last_name' => ['required', 'string', 'max:10'],
            ]);

            $users =  User::where('created_at', '>', Carbon::now()->subMinutes(10))->where('ip_address',User::get_client_ip())->count();
            if($users >= 3){
                return redirect()->back()->with('error', 'You have cross maximum number of registration');
            }


            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->user_email,
                'password' => Hash::make($request->user_password),
                'country' => $request->country,
                'phone' => $request->phone,
            ]);

            $user['writer_subject'] = "You have successfully registered with " . url('/');
            $user['admin_subject'] = "New User " . $user->id . " registered on your site";
            $user['client_subject'] = "You have successfully registered";
            // $this->setUpMailConfig(MessageConfig::NEW_CLIENT_REGISTER, $user);

            try {
                Order::setUpMailConfig(MessageConfig::NEW_CLIENT_REGISTER, $user);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
            $this->user()->login($user);
        } else {
            $request->validate([
                'email' => 'email|exists:users,email'
            ]);

            $credentials = $request->only('email', 'password');
            if (!$this->user()->attempt($credentials)) {
                $this->sendFailedLoginResponse($request);
            }
        }

        $user = $this->user()->user();

        if (isset($request->reffer_user_id) && $this->user()->user()->id == $request->reffer_user_id) {
            $response['message'] = 'You can not refer yourself';
            return response()->json($response, 400);
        }

        $order = new Order();
        $order->forceFill([
            'user_id' => $user->id,
            'email' => $user->email,
            'typeofwork' => $request->typeofwork,
            'urgency' => $request->urgency,
            'words' => $request->words,
            'pages' => $request->pages,
            'academy' => $request->academy,
            'spacing' => $request->spacing,
            'price' => $request->price,
            'topic' => $request->topic,
            'subject' => $request->subject,
            'status' => Order::PENDINGPAYPAL,
            'writer_amount' => Order::writerAmount($request->price, isset($request->reffer_user_id)),
            'sources' => $request->sources,
            'referencing_style' => $request->referencing_style,
            'instructions' => isset($request->instructions) ? $request->instructions : null,
            'affiliate_user_id' => $request->reffer_user_id,
            'discount_percentage' => $request->ref_discount,
            'reference' => $request->reference,
            'slides' => $request->slides,
        ])->save();
        try {
            OrderPlacedTagJob::dispatch($user);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        if (isset($request->reffer_user_id)) {
            if (Cache::has('refferal-' .  $request->reffer_user_id)) {
                Refferal::updateOrCreate(
                    [
                        'ref_id' => $request->reffer_user_id,
                        'user_ip' => $request->ip()
                    ],
                    [
                        'order_id' => $order->id
                    ]
                );
            } else {
                $uniqid = uniqid();
                Refferal::create(
                    [
                        'ref_id' => $request->reffer_user_id,
                        'user_ip' => $request->ip(),
                        'order_id' => $order->id,
                        'session_id' => $uniqid
                    ]
                );
            }
            Session::put('reffer_user_id', @$request->reffer_user_id);
        }

        if ($request->user_target == null && isset($request->preferredwriter)) {
            $orderBid = new OrderBid();
            $orderBid->order_id = $order->id;
            $orderBid->writer_id = $request->preferredwriter;
            $orderBid->user_id = $this->user()->user()->id;
            $orderBid->topic = $request->topic;
            $orderBid->words =  $request->words;
            $orderBid->status =  Order::PENDING;
            $orderBid->is_preferred =  OrderBid::PREFERRED;
            $orderBid->save();
        }

        if ($request->hasFile('materials')) {
            $materials = $request->file('materials');
            foreach ($materials as $material) {
                $fileName =  'material-' . time() . '.' . $material->getClientOriginalExtension();
                $file = $material->storeAs('materials', $fileName, 'public');

                $materialObj = new OrderMaterial();
                $materialObj->forceFill([
                    'order_id' => $order->id,
                    'user_id' => $this->user()->user()->id,
                    'writer_id' => isset($request->preferredwriter) ? null : null,
                    'type' => 2,
                    'name' => $this->user()->user()->full_name,
                    'uploaded_by' => $this->user()->user()->id,
                    'file' => OrderMaterial::fileSave($material, $order->id) ?? ''
                ])->save();
            }
        }

        $response['status'] = true;
        $response['message'] = 'order proceed';
        $response['data'] = Order::with(['userDetails', 'materials'])->where('id', $order->id)->first();

        $walletAmount  = Wallet::where('user_id', $this->user()->user()->id)->sum('amount');
        if ($walletAmount > Wallet::MIN_AMOUNT) {
            $response['wallet']  = $walletAmount;
        }

        // $writerEmail = Writer::select('id', 'email')->where('status', Writer::ACTIVE)->get();
        // $subadminEmails = Admin::select('id', 'email')->where('id', '>', '1')->get();
        $orderDetails = $order;
        // // $orderDetails['writer_subject'] = "A new Order #" . $order->id . " was has been posted!";
        // $orderDetails['admin_subject'] = "New Order #" .  $order->id . " was sucessfully Posted";
        $orderDetails['client_subject'] = "Your Order #" .  $order->id . " was sucessfully received";
        $orderDetails['orderdirectlink'] = env('WRITERURL') . '/writer/order/' . $order->id . '/view';
        $orderDetails['status'] = $this->orderStatusDefine($order->status);
        $orderDetails['first_name'] = $user->first_name;
        // $this->setUpMailConfig(MessageConfig::NEW_ORDER_PLACED, $orderDetails, $writerEmail,$subadminEmails);

        try {
            Log::alert(MessageConfig::NEW_ORDER_PLACED);
            $order_id = $order->id;
            Log::info('the log before cliet dispatch');
            UserSendEmail::dispatch($order_id);
        } catch (\Exception $e) {
            Log::info('catchcatchcatch');
            Log::error($e->getMessage());
        }
        return response()->json($response);
    }

    /**
     * payPal payment of order.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function payPalPayment(Request $request)
    {
        $mainAmount = $request->payableAmount;
        $walletAmount  = Wallet::where('user_id', $this->user()->user()->id)->sum('amount');

        if (isset($request->isWalletAdd) && $request->isWalletAdd == 1) {
            $refferUserId = Session::get('reffer_user_id');
            if ($mainAmount <= $walletAmount) {
                Wallet::create(
                    [
                        'order_id' => $request->order_id,
                        'user_id' => $refferUserId ?? $this->user()->user()->id,
                        'morph_model' => User::class,
                        'amount' =>  -$mainAmount,
                        'description' => 'User #' . $refferUserId ?? $this->user()->user()->id . ' has reffer the order #' . $request->order_id,
                        'status' => Wallet::REDEEM
                    ]
                );
                $order = Order::whereId($request->order_id)->first();
                $order->payment_status = 1;
                $order->status =  Order::OPENPROJECT;
                $order->save();

                $order['writer_subject'] = null;
                $order['admin_subject'] = "Congratulations Payment receive for order #" . $order->id;
                $order['client_subject'] = "Congratulations Payment receive for order #" . $order->id;
                $order['status'] = $this->orderStatusDefine($order->status);
                try {
                    Log::info('the log before admin dispatch (new order placed)');
                    SendEmailJob::dispatch($order->id);
                    $this->setUpMailConfig(MessageConfig::NEW_ORDER_PAID, $order);
                    WriterSendJob::dispatch($order->id);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
                Session::put('success', 'Payment success !!');
                return redirect()->route('thankYou');
            } else {
                $mainAmount =  $request->payableAmount - $walletAmount;
                Session::put('deductWalletAmount', $walletAmount);
                //    Wallet::create(
                //     [
                //         'order_id' => $request->order_id,
                //         'user_id' => $refferUserId ?? $this->user()->user()->id,
                //         'morph_model' => User::class,
                //         'amount' =>  -$walletAmount,
                //         'description' => 'User #' .$this->user()->user()->id.' pay amount '.$walletAmount .' against order ' . $request->order_id,
                //         'status' => Order::COMPLETE
                //     ]
                // );
            }
        }

        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $item_1 = new Item();

        $item_1->setName('Product 1')->setCurrency('USD')->setQuantity(1)->setPrice($mainAmount);

        $item_list = new ItemList();
        $item_list->setItems(array($item_1));

        $amount = new Amount();
        $amount->setCurrency('USD')->setTotal($mainAmount);

        $transaction = new Transaction();
        $transaction->setAmount($amount)->setItemList($item_list)->setDescription('Enter Your transaction description');

        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(route('paymentStatus'))->setCancelUrl(route('paymentStatus'));

        $payment = new Payment();
        $payment->setIntent('Sale')->setPayer($payer)->setRedirectUrls($redirect_urls)->setTransactions(array($transaction));
        try {
            $payment->create($this->_api_context);
        } catch (\PayPal\Exception\PPConnectionException $ex) {

            if (Config::get('app.debug')) {
                Session::put('error', 'Connection timeout');
                return redirect()->route('orderForm');
            } else {
                Session::put('error', 'Some error occur, sorry for inconvenient');
                return redirect()->route('orderForm');
            }
        }

        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }

        Session::put('transaction_id', $payment->getId());
        Session::put('order_id', @$request->order_id);

        if (isset($redirect_url)) {
            return redirect()->away($redirect_url);
        }

        Session::put('error', 'Unknown error occurred');
        return redirect()->route('orderForm');
    }

    /**
     * payment status of payPal.
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     *
     */
    public function paymentStatus(Request $request)
    {
        $transaction_id = Session::get('transaction_id');
        $orderId = Session::get('order_id');

        Session::forget('transaction_id');
        if (empty($request->input('PayerID')) || empty($request->input('token'))) {
            Session::put('error', 'Payment failed');
            return redirect()->route('orderForm');
        }

        $payment = Payment::get($transaction_id, $this->_api_context);
        $execution = new PaymentExecution();
        $execution->setPayerId($request->input('PayerID'));
        $result = $payment->execute($execution, $this->_api_context);

        $order = Order::whereId($orderId)->first();
        if ($result->getState() == 'approved') {
            $deductWalletAmount = Session::get('deductWalletAmount');
            if (!empty($deductWalletAmount)) {
                Wallet::create(
                    [
                        'order_id' => $orderId,
                        'user_id' => $refferUserId ?? $this->user()->user()->id,
                        'morph_model' => User::class,
                        'amount' =>  -$deductWalletAmount,
                        'description' => 'User #' . $this->user()->user()->id . ' pay amount ' . $deductWalletAmount . ' against order ' . $orderId,
                        'status' => Order::COMPLETE
                    ]
                );
                Session::forget('deductWalletAmount');
            }
            $order->payment_status = 1;
            $order->status =  Order::OPENPROJECT;
            $order->transaction_id = $result->transactions[0]->related_resources[0]->sale->id;
            $order->save();

            try {
                if (env('CONVERTKIT_API_KEY') !== null && env('CONVERTKIT_API_secret') !== '' && env('CONVERTKIT_SUBSCRIBE_FORM_ID') !== '') {
                    try {
                        $api = new \ConvertKit_API\ConvertKit_API(env('CONVERTKIT_API_KEY'), env('CONVERTKIT_API_secret'));
                        if ($api) {
                            $subscriber_id = $api->get_subscriber_id($this->user()->user()->email);
                            Log::info($subscriber_id);
                            if ($subscriber_id) {
                                $subscriber_tags = $api->get_subscriber_tags($subscriber_id);
                                $array = json_decode(json_encode($subscriber_tags), true);
                                foreach ($array['tags'] as  $value) {
                                    $available_tags[] = $value['id'];
                                }
                                if (!empty($available_tags)) {
                                    if (($key = array_search(Order::UW911_CUSTOMER_TAG_ID, $available_tags)) == FALSE) {
                                        $api->add_tag(Order::UW911_CUSTOMER_TAG_ID, [
                                            'email' => $this->user()->user()->email
                                        ]);
                                        Log::info('Uw 911 Customer tag added');
                                    }
                                    if (($key = array_search(Order::NEW_ORDER_PLACED_TAG_ID, $available_tags)) !== FALSE) {
                                        $api->remove_tag(Order::NEW_ORDER_PLACED_TAG_ID, [
                                            'email' => $this->user()->user()->email
                                        ]);
                                        Log::info('order placed tag removed');
                                    }
                                    if (($key = array_search(Order::NEW_ORDER_PAID_TAG_ID, $available_tags)) == FALSE) {
                                        $api->add_tag(Order::NEW_ORDER_PAID_TAG_ID, [
                                            'email' => $this->user()->user()->email
                                        ]);
                                        Log::info('order paid tag added');
                                    }
                                } else {
                                    Log::info('outside available_tags tag');

                                    $api->add_tag(Order::UW911_CUSTOMER_TAG_ID, [
                                        'email' => $this->user()->user()->email
                                    ]);
                                    $api->add_tag(Order::NEW_ORDER_PLACED_TAG_ID, [
                                        'email' => $this->user()->user()->email
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }

            $order['writer_subject'] = null;
            $order['admin_subject'] = "Congratulations Payment receive for order #" . $order->id;
            $order['client_subject'] = "Congratulations Payment receive for order #" . $order->id;
            $order['status'] = $this->orderStatusDefine($order->status);
            // $this->setUpMailConfig(MessageConfig::NEW_ORDER_PAID, $order);
            try {
                Log::info('the log before admin dispatch (new order placed)');
                SendEmailJob::dispatch($order->id);
                $this->setUpMailConfig(MessageConfig::NEW_ORDER_PAID, $order);
                WriterSendJob::dispatch($order->id);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
            $refferUserId = Session::get('reffer_user_id');
            if (!empty($refferUserId)) {
                Wallet::create(
                    [
                        'order_id' => $orderId,
                        'user_id' => $refferUserId,
                        'morph_model' => User::class,
                        'amount' =>  Refferal::commission(),
                        'description' => 'User #' . $refferUserId . ' has reffer the order #' . $orderId,
                        'status' => Order::PENDING
                    ]
                );

                $order['admin_subject'] = "New Refferal Order #" .  $orderId . " was sucessfully Posted";
                try {
                    Order::setUpMailConfig(MessageConfig::NEW_REFFERAL_ORDER_PLACED, $order);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            }
            Session::forget('order_id');
            Session::forget('reffer_user_id');
            Session::forget('deductWalletAmount');
            Session::put('success', 'Payment success !!');
            return redirect()->route('thankYou');
        } else {
            $order->payment_status = 0;
            $order->status = Order::PENDINGPAYPAL;
            $order->save();
        }

        Session::forget('order_id');
        Session::forget('reffer_user_id');
        Session::forget('deductWalletAmount');
        Session::put('error', 'Payment failed !!');
        return redirect()->route('orderForm');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function user()
    {
        return Auth::guard('user');
    }

    public function assignReferral($hash)
    {
        return redirect()->route('user.affilate.dashboard');
    }

    public function affilateUserOrderAction(Request $request, $id)
    {
        $hashids = new Hashids(env('HASHID_KEY', ''), 5);
        $ref = $hashids->decode($id);
        if (!$ref) {
            return redirect()->route('welcome.page');
        }
        $uniqid = uniqid();
        $reffer_user_id = $ref[0];

        $expiresAt = Carbon::now()->addMinutes(3);
        Cache::put('refferal-' . $reffer_user_id, true, $expiresAt);

        Refferal::updateOrCreate(
            [
                'ref_id' => $reffer_user_id,
                'user_ip' => $request->ip()
            ],
            [
                'session_id' => $uniqid
            ]
        );

        return view('user.order.order-form', [
            'prices' => Price::get(),
            'subjects' => Subject::get(),
            'spacings' => Spacing::get(),
            'referencingStyles' => ReferencingStyle::get(),
            'typeofworks' => Price::pluck('typeofwork')->unique(),
            'user' => User::select('id', 'first_name', 'last_name')->whereId($reffer_user_id)->first(),
            'reffer_user_id' => $reffer_user_id
        ]);
    }

    public function productwidget($ref)
    {
        $url = route('affilate.make.order', ['id' => $ref]);
        return view('user.affiliate.product_widget', compact('url', 'ref'));
    }
    public function thankYou()
    {
        //  dd(99);
        return view('user.order.thank_you_message');
    }
    public function orderStatusChange(Request $request, $id)
    {
        $rules = [
            'status' => ['required'],
            'due_date' => ['nullable']
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput()->with('error');
        }

        $order = Order::whereId($id)->first();
        if ($order) {
            $order->status = $request->status;
            $order->save();
        }

        OrderHistory::create(
            [
                'order_id' => $order->id,
                'actionable_id' =>  Auth::user()->id,
                'actionable_model' =>  'App/Models/User',
                'actionable_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
                'status' => $request->status,
                'description' => 'Client change Order #' . $order->id . '  status to ' . Order::orderStatusDefine($request->status)
            ]
        );

        $order = OrderBid::whereorder_id($id)->first();
        if ($order) {
            $order->status = $request->status;
            $order->save();
        }
        $writer = Writer::whereId($order->writer_id)->first();

        $client = User::select('first_name', 'email')->whereId($order->user_id)->first();
        $order['writer_subject'] = "Order #" . $order->order_id . ' marked as/for' . $this->orderStatusDefine($order->status);
        $order['admin_subject'] = "Order #" . $order->order_id . ' has been marked as/for  ' . $this->orderStatusDefine($order->status);
        $order['client_subject'] = "Your Order #" . $order->order_id . ' has been marked as/for' . $this->orderStatusDefine($order->status);
        $order['status'] = $this->orderStatusDefine($order->status);
        $order['email'] = $writer->email;
        $order['client_first_name'] = $client->first_name;
        $order['client_email'] = $client->email;
        $order['id'] = $order->order_id;
        $order['order_id'] = $order->order_id;
        // dd($order);
        // $this->setUpMailConfig(MessageConfig::ORDER_STATUS_CHANGED, $order);
        try {
            Order::setUpMailConfig(MessageConfig::ORDER_STATUS_CHANGED, $order);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        if ($request->status == Order::REVISION) {
            $order_revision = OrderRevision::where('order_id', $id)->first();
            if ($order_revision) {
                $order_revision->due_date = $request->due_date;
                $order_revision->status = $request->status;
                $order_revision->save();
            } else {
                $order_revision = OrderRevision::create(
                    [
                        'order_id' => $id,
                        'due_date' => $request->due_date,
                        'status' => $request->status
                    ]
                );
            }
        }


        return redirect()->back()->with('success', 'Order Status has been successfully Updated.');
    }
}
