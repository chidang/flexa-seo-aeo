/**
 * Single source of truth for the WhatsApp click-to-chat target.
 * Both the floating FAB (rendered on every page via app/layout.tsx)
 * and the Services page CTAs import from here, so changing the number
 * is a one-line edit.
 */

/** Plain digits, no '+', for the wa.me URL. */
export const WHATSAPP_NUMBER = '84905648626';

/** Human-readable label for the contact card. */
export const WHATSAPP_DISPLAY = '+84 905 648 626';

/** Pre-filled opener so the recipient sees context, not a cold ping. */
const WHATSAPP_DEFAULT_TEXT = "Hi! I'd like to discuss a custom build.";

export const WHATSAPP_URL = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(
  WHATSAPP_DEFAULT_TEXT,
)}`;
