/**
 * Normalize phone number into standard international format for WhatsApp Cloud API.
 * Cleans spaces, dashes, parentheses, and handles common country prefix anomalies.
 */
export function normalizePhoneNumber(phone: string, defaultCountryCode = '91'): string {
  if (!phone) return '';

  // Remove any character except digits
  let cleaned = phone.replace(/\D/g, '');

  // Remove leading zeroes
  cleaned = cleaned.replace(/^0+/, '');

  if (cleaned.length === 0) return '';

  // If phone is 10 digits (typical mobile number in India/US without code)
  if (cleaned.length === 10) {
    return `${defaultCountryCode}${cleaned}`;
  }

  return cleaned;
}

/**
 * Format phone for display (e.g., +91 98765 43210)
 */
export function formatPhoneDisplay(phone: string): string {
  if (!phone) return '';
  const cleaned = phone.replace(/\D/g, '');
  if (cleaned.length === 12 && cleaned.startsWith('91')) {
    return `+91 ${cleaned.slice(2, 7)} ${cleaned.slice(7)}`;
  }
  return `+${cleaned}`;
}
