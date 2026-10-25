import { onBeforeUnmount, onMounted, type Ref } from 'vue';

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export interface ModalDialog {
    /** True when a click on the dialog landed on its backdrop rather than on its content. */
    isBackdropClick: (event: MouseEvent) => boolean;
}

/**
 * Shows a native modal <dialog> while the component is mounted: the rest of the page is inert,
 * the focus moves to the first control inside and goes back to the element that opened it on
 * unmount. Esc fires the dialog's `cancel` event; the component prevents it and decides whether
 * to close, so its parent's state stays the only source of truth.
 */
export function useModalDialog(dialog: Ref<HTMLDialogElement | null>): ModalDialog {
    let opener: HTMLElement | null = null;

    onMounted(() => {
        opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        const element = dialog.value;
        if (element === null) return;

        if (!element.open) {
            element.showModal();
        }
        if (!element.contains(document.activeElement)) {
            element.querySelector<HTMLElement>(FOCUSABLE)?.focus();
        }
    });

    onBeforeUnmount(() => {
        if (dialog.value?.open) {
            dialog.value.close();
        }
        if (opener?.isConnected) {
            opener.focus();
        }
        opener = null;
    });

    return {
        isBackdropClick: (event) => event.target === dialog.value,
    };
}
