<template>
    <a href="#" @click.prevent="createPoll" class="block px-4 py-2 text-sm">
        New Poll
    </a>
</template>

<script>
export default {
    name: 'InboxNewPoll',
    methods: {
        createPoll() {
            const bus = Statamic.$activitypub.bus;
            if (typeof bus.emit === 'function') {
                bus.emit('activitypub:inbox:create-poll');
            } else {
                bus.$emit('activitypub:inbox:create-poll');
            }
        }
    }
}
</script>
