<x-form-row>
    <x-input name="name" label="Schedule Name" :required="true" />
    <x-select name="frequency" label="Frequency" :options="['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly']" :required="true" />
</x-form-row>
<x-textarea name="description" label="Description" />
