const GOAL_PRESET_META = {
    sales_qualification: {
        labelKey: 'flow_builder.goal.sales_qualification.label',
        descriptionKey: 'flow_builder.goal.sales_qualification.description',
        placeholderKey: 'flow_builder.goal.sales_qualification.placeholder',
    },
    support_routing: {
        labelKey: 'flow_builder.goal.support_routing.label',
        descriptionKey: 'flow_builder.goal.support_routing.description',
        placeholderKey: 'flow_builder.goal.support_routing.placeholder',
    },
    appointment_booking: {
        labelKey: 'flow_builder.goal.appointment_booking.label',
        descriptionKey: 'flow_builder.goal.appointment_booking.description',
        placeholderKey: 'flow_builder.goal.appointment_booking.placeholder',
    },
    seller_intake: {
        labelKey: 'flow_builder.goal.seller_intake.label',
        descriptionKey: 'flow_builder.goal.seller_intake.description',
        placeholderKey: 'flow_builder.goal.seller_intake.placeholder',
    },
};

const translate = (value, t) => {
    if (!t) {
        return value;
    }

    const translated = t(value);

    return translated === value ? value : translated;
};

export const flowGoalPresetOptions = (t = (value) => value) => Object.entries(GOAL_PRESET_META).map(([value, meta]) => ({
    value,
    label: translate(meta.labelKey, t),
    description: translate(meta.descriptionKey, t),
}));

export const flowGoalPresetLabel = (value, t = (entry) => entry) => {
    const meta = GOAL_PRESET_META[String(value || '').trim()] || GOAL_PRESET_META.sales_qualification;

    return translate(meta.labelKey, t);
};

export const flowGoalPresetDescription = (value, t = (entry) => entry) => {
    const meta = GOAL_PRESET_META[String(value || '').trim()] || GOAL_PRESET_META.sales_qualification;

    return translate(meta.descriptionKey, t);
};

export const flowGoalPresetPlaceholder = (value, t = (entry) => entry) => {
    const meta = GOAL_PRESET_META[String(value || '').trim()] || GOAL_PRESET_META.sales_qualification;

    return translate(meta.placeholderKey, t);
};
