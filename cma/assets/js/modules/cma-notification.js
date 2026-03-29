/**
 * Global notification utility for CMA
 * Uses lib-toaster component via libToast
 *
 * @module cma-notification
 */

const cmaNotification = {
    /**
     * Show a notification message
     * @param {string} message - Message to display
     * @param {string} type - 'success', 'error', 'warning', or 'info'
     */
    show(message, type = 'info') {
        if (typeof libToast !== 'undefined' && libToast[type]) {
            libToast[type](message);
        } else if (typeof libToast !== 'undefined') {
            libToast.info(message);
        }
    },

    /**
     * Show success notification
     * @param {string} message - Message to display
     */
    success(message) {
        if (typeof libToast !== 'undefined') {
            libToast.success(message);
        }
    },

    /**
     * Show error notification
     * @param {string} message - Message to display
     */
    error(message) {
        if (typeof libToast !== 'undefined') {
            libToast.error(message);
        }
    },

    /**
     * Show warning notification
     * @param {string} message - Message to display
     */
    warning(message) {
        if (typeof libToast !== 'undefined') {
            libToast.warning(message);
        }
    },

    /**
     * Show info notification
     * @param {string} message - Message to display
     */
    info(message) {
        if (typeof libToast !== 'undefined') {
            libToast.info(message);
        }
    }
};

// Attach to window for backward compatibility
if (typeof window !== 'undefined') {
    window.cmaNotification = cmaNotification;
}

export default cmaNotification;
