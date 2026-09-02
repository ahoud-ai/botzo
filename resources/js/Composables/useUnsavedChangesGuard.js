import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Warns the user before they navigate away (tab close/reload or an in-app
 * Inertia link) while a form has unsaved changes.
 *
 * Call the returned `markSubmitting()` right before triggering the form's
 * own save/delete visit, so that action isn't mistaken for the user
 * abandoning their changes and re-confirmed a second time.
 *
 * @param {import('vue').Ref<boolean> | (() => boolean)} isDirty
 * @param {string} message
 * @returns {{ markSubmitting: () => void }}
 */
export function useUnsavedChangesGuard(isDirty, message) {
  const dirty = () => (typeof isDirty === 'function' ? isDirty() : isDirty.value);
  let isSubmittingOwnForm = false;

  const markSubmitting = () => {
    isSubmittingOwnForm = true;
  };

  const handleBeforeUnload = (event) => {
    if (!dirty()) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
  };

  const removeInertiaGuard = router.on('before', (event) => {
    if (isSubmittingOwnForm) {
      isSubmittingOwnForm = false;
      return;
    }

    if (!dirty()) {
      return;
    }

    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
  });

  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeInertiaGuard();
  });

  return { markSubmitting };
}
