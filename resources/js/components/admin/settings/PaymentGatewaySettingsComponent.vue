<template>
    <div class="payment-gateway-settings">
        <div class="card">
            <div class="card-header">
                <h5>{{ $t('Stripe Connect Settings') }}</h5>
            </div>
            <div class="card-body">
                <div class="setting-form">
                    <p class="mb-4">{{ $t('label.connect_with_stripe') }} {{ $t('message.to_securely_process_payments') }}</p>
                    
                    <!-- Merchant ID is auto-generated and hidden from the user -->
                    <stripe-connect-button :merchant-id="merchantId" />
                    
                    <!-- Display connection status if available -->
                    <div v-if="stripeSettings.connected" class="mt-4 alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i> {{ $t('message.stripe_connected_successfully') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "PaymentGatewaySettingsComponent",
    data() {
        return {
            merchantId: '',
            stripeSettings: {
                enabled: false,
                apiKey: '',
                secretKey: '',
                mode: 'test',
                connected: false
            }
        }
    },
    mounted() {
        // Auto-generate merchant ID based on restaurant/branch ID or user ID
        this.generateMerchantId();
        
        // Load Stripe settings
        this.getStripeSettings();
    },
    methods: {
        generateMerchantId() {
            // First priority: Use restaurant/branch ID if available
            if (this.$store.getters['auth/restaurant']) {
                this.merchantId = `merchant_${this.$store.getters['auth/restaurant'].id}`;
            } 
            // Second priority: Use user ID if available
            else if (this.$store.getters['auth/user']) {
                this.merchantId = `merchant_${this.$store.getters['auth/user'].id}`;
            } 
            // Fallback: Generate a unique ID using timestamp
            else {
                this.merchantId = `merchant_${Date.now()}`;
            }
            
            console.log("Auto-generated Merchant ID:", this.merchantId);
        },
        getStripeSettings() {
            // This would typically fetch your Stripe settings from the API
            axios.get('/api/v1/admin/payment-gateway', {
                params: { slug: 'stripe' }
            }).then(response => {
                if (response.data.data && response.data.data.length > 0) {
                    const stripeGateway = response.data.data.find(gateway => gateway.slug === 'stripe');
                    if (stripeGateway) {
                        // Map Stripe gateway options to our local state
                        const options = stripeGateway.options || [];
                        
                        options.forEach(option => {
                            if (option.option === 'stripe_key') {
                                this.stripeSettings.apiKey = option.value || '';
                            }
                            if (option.option === 'stripe_secret') {
                                this.stripeSettings.secretKey = option.value || '';
                            }
                            if (option.option === 'stripe_mode') {
                                this.stripeSettings.mode = option.value || 'test';
                            }
                            if (option.option === 'stripe_status') {
                                this.stripeSettings.enabled = option.value === 1;
                            }
                            // Check if account is connected (you might need to add this option to your database)
                            if (option.option === 'stripe_connected') {
                                this.stripeSettings.connected = option.value === 1;
                            }
                        });
                    }
                }
            }).catch(error => {
                console.error('Error fetching Stripe settings:', error);
                this.$toastr.error('Failed to load Stripe settings');
            });
        }
    }
}
</script>

<style scoped>
.payment-gateway-settings {
    padding: 20px 0;
}
</style> 