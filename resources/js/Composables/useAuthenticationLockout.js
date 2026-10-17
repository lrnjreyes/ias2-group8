import { computed, onScopeDispose, ref, watch } from 'vue';

export function useAuthenticationLockout(getStatus, prefix, isRelevant = () => true) {
    const remaining = ref(0);
    let timer;

    const stopTimer = () => {
        clearInterval(timer);
        timer = undefined;
    };

    watch(getStatus, (status) => {
        stopTimer();
        remaining.value = Math.max(0, status?.retryAfter ?? 0);
        if (!remaining.value) return;

        const deadline = performance.now() + remaining.value * 1000;
        timer = setInterval(() => {
            remaining.value = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));
            if (!remaining.value) stopTimer();
        }, 250);
    }, { immediate: true });

    onScopeDispose(stopTimer);

    const isLocked = computed(() => isRelevant() && remaining.value > 0);
    const message = computed(() => {
        const status = getStatus();
        if (!status || !isRelevant()) return '';
        if (!remaining.value) return 'You can try again now.';
        if (!status.showCountdown) return 'Too many attempts. Please try again later.';

        return `${prefix} Please try again in ${remaining.value} ${remaining.value === 1 ? 'second' : 'seconds'}.`;
    });

    return { isLocked, message };
}
