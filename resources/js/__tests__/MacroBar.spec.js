import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MacroBar from '../components/MacroBar.vue'

describe('MacroBar', () => {
    it('renders percentage of goal as width', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Protein', value: 60, goal: 120, color: 'bg-emerald-500' } })

        const bar = wrapper.get('.bg-emerald-500')
        expect(bar.attributes('style')).toContain('width: 50%')
        expect(wrapper.text()).toContain('60 / 120')
    })

    it('clamps percentage at 100', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Fat', value: 500, goal: 100, color: 'bg-rose-500' } })

        const bar = wrapper.get('.bg-rose-500')
        expect(bar.attributes('style')).toContain('width: 100%')
    })

    it('shows only the value when goal is null', () => {
        const wrapper = mount(MacroBar, { props: { label: 'Protein', value: 30, goal: null } })

        expect(wrapper.text()).toContain('30')
        expect(wrapper.text()).not.toContain('null')
    })
})
