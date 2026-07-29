const prefersReducedMotion = () =>
    typeof window !== 'undefined'
    && window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const vReveal = {
    mounted(el, binding) {
        if (prefersReducedMotion()) {
            return;
        }

        const delay = Number(binding.value?.delay ?? binding.value ?? 0) || 0;

        el.classList.add('reveal-init');
        if (delay > 0) {
            el.style.transitionDelay = `${delay}ms`;
        }

        // threshold is a ratio of the element's OWN height, so a tall full-page
        // section (e.g. 1000px+) needs hundreds of px on screen before a 0.15
        // ratio is reached - it sat invisible well after scrolling into view.
        // A near-zero threshold reveals as soon as the section starts entering,
        // regardless of how tall it is.
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.classList.add('reveal-visible');
                    observer.unobserve(el);
                }
            },
            { threshold: 0, rootMargin: '0px 0px -10% 0px' },
        );

        observer.observe(el);
        el.__revealObserver = observer;
    },
    unmounted(el) {
        el.__revealObserver?.disconnect();
    },
};
