<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayOption;
use Illuminate\Support\Facades\Log;

class StripeConnectController extends Controller
{
    private $stripeConnectUrl;
    private $redirectUrl;
    private $clientId;
    private $secretKey;
    private $stripeMode;

    public function __construct()
    {
        // Get these values from the database or environment
        $paymentGateway = PaymentGateway::with('gatewayOptions')->where(['slug' => 'stripe'])->first();
        
        if (!blank($paymentGateway)) {
            $this->paymentGatewayOption = $paymentGateway->gatewayOptions->pluck('value', 'option');
            $this->stripeMode = $this->paymentGatewayOption['stripe_mode'] ?? 'live';
            $this->secretKey = $this->paymentGatewayOption['stripe_secret'] ?? '';
            
            // Set client IDs based on mode
            if ($this->stripeMode === 'live') {
                $this->clientId = env('STRIPE_CLIENT_ID', 'ca_PLRd2Pzs8C3mkhIZPMpVt6KH62MFqCwE');
            } else {
                $this->clientId = env('STRIPE_TEST_CLIENT_ID', 'ca_PLRdEVKs3KZA2Rc6NIC8mKwqI6CN0dxm');
            }
            
            $this->stripeConnectUrl = 'https://connect.stripe.com/oauth/authorize';
            $this->redirectUrl = config('app.url') . '/admin/stripe-connect/callback';
        }
    }

    /**
     * Generate a Connect with Stripe URL
     */
    public function getConnectUrl(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (empty($merchantId)) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant ID is required'
            ], 422);
        }

        // Create state parameter with merchant info
        $state = base64_encode(json_encode([
            'merchant_id' => $merchantId,
            'mode' => $this->stripeMode
        ]));

        // Build the OAuth URL
        $url = $this->stripeConnectUrl . "?response_type=code&client_id={$this->clientId}&scope=read_write&state={$state}";
        
        return response()->json([
            'status' => true,
            'connect_url' => $url
        ]);
    }

    /**
     * Handle the OAuth callback from Stripe
     */
    public function handleCallback(Request $request)
    {
        try {
            $code = $request->code;
            $state = $request->state;
            
            if (empty($code)) {
                return redirect()->route('admin.dashboard')->with('error', 'Authorization code is missing');
            }
            
            // Decode state
            $stateData = json_decode(base64_decode($state), true);
            
            if (empty($stateData['merchant_id'])) {
                return redirect()->route('admin.dashboard')->with('error', 'Merchant ID is missing');
            }
            
            // Make API request to connect.trilogypos.com
            $response = Http::post("https://connect.trilogypos.com/oauth/connect", [
                'code' => $code,
                'merchant_id' => $stateData['merchant_id'],
                'mode' => $stateData['mode'] ?? 'live'
            ]);
            
            if ($response->successful()) {
                // Store connection status in the database
                $this->updateStripeConnectedStatus(true);
                
                return redirect()->route('admin.settings.stripe-connect')->with('success', 'Successfully connected with Stripe');
            } else {
                Log::error('Stripe Connect Error: ' . $response->body());
                return redirect()->route('admin.settings.stripe-connect')->with('error', 'Failed to connect with Stripe: ' . $response->json('message', 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Stripe Connect Exception: ' . $e->getMessage());
            return redirect()->route('admin.settings.stripe-connect')->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
    
    /**
     * Update Stripe connected status in database
     */
    private function updateStripeConnectedStatus(bool $connected)
    {
        try {
            $paymentGateway = PaymentGateway::where(['slug' => 'stripe'])->first();
            
            if (!blank($paymentGateway)) {
                // Check if the stripe_connected option already exists
                $connectedOption = PaymentGatewayOption::where([
                    'payment_gateway_id' => $paymentGateway->id,
                    'option' => 'stripe_connected'
                ])->first();
                
                if ($connectedOption) {
                    // Update existing option
                    $connectedOption->value = $connected ? 1 : 0;
                    $connectedOption->save();
                } else {
                    // Create new option
                    PaymentGatewayOption::create([
                        'payment_gateway_id' => $paymentGateway->id,
                        'option' => 'stripe_connected',
                        'value' => $connected ? 1 : 0
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update Stripe connected status: ' . $e->getMessage());
        }
    }
} 