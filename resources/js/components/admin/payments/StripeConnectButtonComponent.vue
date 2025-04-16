<template>
    <div class="stripe-connect-container">
        <button 
            @click="connectWithStripe" 
            class="stripe-connect-button"
            :disabled="loading"
        >
            <span v-if="loading">
                <i class="fas fa-spinner fa-spin mr-1"></i> Processing...
            </span>
            <span v-else>
                <i class="fab fa-stripe mr-1"></i> Connect with Stripe
            </span>
        </button>
    </div>
</template>

<script>
export default {
    name: 'StripeConnectButtonComponent',
    props: {
        merchantId: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            loading: false
        }
    },
    methods: {
        async connectWithStripe() {
            try {
                this.loading = true;
                const response = await axios.get('/admin/stripe-connect/url', {
                    params: {
                        merchant_id: this.merchantId
                    }
                });
                
                if (response.data.status) {
                    window.location.href = response.data.connect_url;
                } else {
                    this.$toastr.error(response.data.message || 'Failed to generate Stripe Connect URL');
                }
            } catch (error) {
                this.$toastr.error(error.response?.data?.message || 'An error occurred while connecting with Stripe');
                console.error('Stripe Connect Error:', error);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<style scoped>
.stripe-connect-container {
    margin: 15px 0;
}

.stripe-connect-button {
    background-color: #635bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
}

.stripe-connect-button:hover {
    background-color: #5851db;
}

.stripe-connect-button:disabled {
    background-color: #a5a5a5;
    cursor: not-allowed;
}
</style> 