/**
 * Biometric Authentication Handler
 * Handles WebAuthn API for fingerprint/face recognition
 */

class BiometricAuth {
    constructor() {
        this.apiBase = '/attendance/api/biometric-auth.php';
        this.supported = this.checkSupport();
    }

    /**
     * Check if WebAuthn is supported
     */
    checkSupport() {
        return window.PublicKeyCredential !== undefined &&
               navigator.credentials !== undefined;
    }

    /**
     * Convert base64 to ArrayBuffer
     */
    base64ToArrayBuffer(base64) {
        const binaryString = window.atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes.buffer;
    }

    /**
     * Convert ArrayBuffer to base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    /**
     * Register new biometric credential
     */
    async register() {
        if (!this.supported) {
            throw new Error('Biometric authentication is not supported on this device');
        }

        try {
            // Get registration options from server
            const response = await fetch(`${this.apiBase}?action=register_start`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to start registration');
            }

            // Create credential
            const publicKey = {
                challenge: this.base64ToArrayBuffer(data.challenge),
                rp: {
                    name: 'Attendance System',
                    id: window.location.hostname
                },
                user: {
                    id: this.base64ToArrayBuffer(data.user.id),
                    name: data.user.name,
                    displayName: data.user.displayName
                },
                pubKeyCredParams: [
                    { alg: -7, type: 'public-key' },  // ES256
                    { alg: -257, type: 'public-key' }  // RS256
                ],
                authenticatorSelection: {
                    authenticatorAttachment: 'platform',
                    userVerification: 'required'
                },
                timeout: 60000,
                attestation: 'none'
            };

            const credential = await navigator.credentials.create({ publicKey });

            if (!credential) {
                throw new Error('Failed to create credential');
            }

            // Send credential to server
            const registerData = {
                credential_id: this.arrayBufferToBase64(credential.rawId),
                public_key: this.arrayBufferToBase64(credential.response.getPublicKey()),
                counter: 0,
                device_name: navigator.userAgent.includes('Mobile') ? 'Mobile Device' : 'Desktop Device'
            };

            const completeResponse = await fetch(`${this.apiBase}?action=register_complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(registerData)
            });

            const result = await completeResponse.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to complete registration');
            }

            return result;

        } catch (error) {
            console.error('Biometric registration error:', error);
            throw error;
        }
    }

    /**
     * Login with biometric
     */
    async login() {
        if (!this.supported) {
            throw new Error('Biometric authentication is not supported on this device');
        }

        try {
            // Get login options from server
            const response = await fetch(`${this.apiBase}?action=login_start`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to start login');
            }

            // Prepare allowed credentials
            const allowedCredentials = data.allowedCredentials.map(cred => ({
                id: this.base64ToArrayBuffer(cred.id),
                type: cred.type
            }));

            // Get assertion
            const publicKey = {
                challenge: this.base64ToArrayBuffer(data.challenge),
                allowCredentials: allowedCredentials,
                timeout: 60000,
                userVerification: 'required'
            };

            const assertion = await navigator.credentials.get({ publicKey });

            if (!assertion) {
                throw new Error('Biometric verification failed');
            }

            // Send assertion to server
            const loginData = {
                credential_id: this.arrayBufferToBase64(assertion.rawId),
                authenticator_data: this.arrayBufferToBase64(assertion.response.authenticatorData),
                client_data: this.arrayBufferToBase64(assertion.response.clientDataJSON),
                signature: this.arrayBufferToBase64(assertion.response.signature)
            };

            const completeResponse = await fetch(`${this.apiBase}?action=login_complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(loginData)
            });

            const result = await completeResponse.json();

            if (!result.success) {
                throw new Error(result.error || 'Login failed');
            }

            return result;

        } catch (error) {
            console.error('Biometric login error:', error);
            throw error;
        }
    }

    /**
     * Simplified fingerprint scan for attendance
     */
    async quickScan() {
        try {
            const result = await this.login();
            return {
                success: true,
                user: result.user,
                timestamp: new Date().toISOString()
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * List registered credentials
     */
    async listCredentials() {
        const response = await fetch(`${this.apiBase}?action=list_credentials`);
        return await response.json();
    }

    /**
     * Delete credential
     */
    async deleteCredential(credentialId) {
        const response = await fetch(`${this.apiBase}?action=delete_credential`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `credential_id=${encodeURIComponent(credentialId)}`
        });
        return await response.json();
    }
}

// Create global instance
window.biometricAuth = new BiometricAuth();

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BiometricAuth;
}
