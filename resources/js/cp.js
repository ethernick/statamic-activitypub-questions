import PollBox from './components/PollBox.vue';

const boot = () => {
    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
        Statamic.$activitypub.hooks.register('inbox-note-content', {
            component: PollBox,
            priority: 10
        });
    }
};

if (typeof Statamic !== 'undefined') {
    boot();
}
