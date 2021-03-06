<template>
    <app-table-panel
        :title="'Lançamentos (' + pagination.total + ')'"
        titleCollapsed="Lançamento"
        :subTitle="
            congressmen.selected.name + ' - ' + congressmanBudgetsSummaryLabel
        "
        :per-page="perPage"
        :filter-text="filterText"
        @input-filter-text="filterText = $event.target.value"
        @set-per-page="perPage = $event"
        :collapsedLabel="currentSummaryLabel"
        :is-selected="selected.id !== null"
    >
        <template slot="widgets" v-if="can('entries:show')">
            <div class="mr-2">
                <span
                    class="btn btn-sm m-2"
                    :class="{
                        'btn-outline-success':
                            congressmanBudgets.selected.balance >= 0,
                        'btn-outline-danger':
                            congressmanBudgets.selected.balance < 0,
                    }"
                >
                    saldo acumulado |
                    {{ congressmanBudgets.selected.balance_formatted }}
                </span>
            </div>
        </template>

        <template slot="buttons">
            <button
                v-if="can('entries:buttons') || can('entries:store')"
                :disabled="!can('entries:store') || congressmanBudgetsClosedAt"
                class="btn btn-primary btn-sm pull-right"
                @click="createEntry()"
                title="Novo lançamento"
            >
                <i class="fa fa-plus"></i>
            </button>
        </template>

        <app-table
            :pagination="pagination"
            @goto-page="gotoPage($event)"
            :columns="getTableColumns()"
        >
            <tr
                @click="selectEntry(entry)"
                v-for="entry in entries.data.rows"
                :class="{
                    'cursor-pointer': true,
                    'bg-primary-lighter text-white': isCurrent(entry, selected),
                }"
            >
                <td class="align-middle">{{ entry.date_formatted }}</td>

                <td class="align-middle">
                    {{ entry.object }}<br />
                    <span>
                        <small class="text-primary">
                            {{ entry.cost_center_code }} -
                            {{ entry.cost_center_name_formatted }}
                        </small>
                    </span>
                </td>

                <td class="align-middle">
                    {{ entry.name }}
                    <span v-if="entry.cpf_cnpj">
                        <br />
                        <small class="text-primary">
                            {{ entry.cpf_cnpj }}
                            <b class="text-danger">
                                {{
                                    can('entries:show') &&
                                    entry.provider_is_blocked
                                        ? '- Bloqueado pela DOCIGP'
                                        : ''
                                }}
                            </b>
                        </small>
                    </span>
                </td>

                <td class="align-middle text-right">
                    {{ entry.documents_count }}
                </td>

                <td
                    v-if="can('entry-comments:show')"
                    class="align-middle text-right"
                >
                    {{ entry.comments_count }}
                </td>

                <td class="align-middle text-right">
                    {{ entry.value_formatted }}
                </td>

                <td v-if="can('entries:show')" class="align-middle text-center">
                    <span :class="getEntryType(entry).class">
                        {{ getEntryType(entry).name }}
                    </span>
                </td>

                <td v-if="can('entries:show')" class="align-middle text-center">
                    <span
                        class="
                            badge badge-primary"
                    >
                        {{
                            entry.entry_type_name +
                                (entry.document_number
                                    ? ': ' + entry.document_number
                                    : '')
                        }}
                    </span>
                </td>

                <td
                    v-if="can('congressman-budgets:show')"
                    class="align-middle text-center"
                >
                    <app-badge
                        v-if="entry.pendencies.length === 0"
                        caption="não"
                        color="#38c172,#FFFFFF"
                        padding="1"
                    ></app-badge>

                    <app-badge
                        v-if="entry.pendencies.length > 0"
                        color="#e3342f,#FFFFFF"
                        padding="1"
                    >
                        <div v-for="pendency in entry.pendencies">
                            &bull; {{ pendency }}
                        </div>
                    </app-badge>
                </td>

                <td v-if="can('entries:show')" class="align-middle text-center">
                    <app-active-badge
                        :value="entry.verified_at"
                        :labels="['sim', 'não']"
                    ></app-active-badge>
                </td>

                <td v-if="can('entries:show')" class="align-middle text-center">
                    <app-active-badge
                        :value="entry.analysed_at"
                        :labels="['sim', 'não']"
                    ></app-active-badge>
                </td>

                <td v-if="can('entries:show')" class="align-middle text-center">
                    <app-active-badge
                        :value="
                            entry.published_at && !entry.is_transport_or_credit
                        "
                        :labels="['público', 'privado']"
                    ></app-active-badge>
                </td>

                <td class="align-middle text-right">
                    <div>
                        <app-action-button
                            v-if="getEntryState(entry).buttons.verify.visible"
                            :disabled="
                                getEntryState(entry).buttons.verify.disabled
                            "
                            classes="btn btn-sm btn-micro btn-primary"
                            :title="getEntryState(entry).buttons.verify.title"
                            :model="entry"
                            swal-title="Verificar este lançamento?"
                            label="verificar"
                            icon="fa fa-check"
                            store="entries"
                            method="verify"
                        >
                        </app-action-button>

                        <app-action-button
                            v-if="getEntryState(entry).buttons.unverify.visible"
                            :disabled="
                                getEntryState(entry).buttons.unverify.disabled
                            "
                            classes="btn btn-sm btn-micro btn-warning"
                            :title="getEntryState(entry).buttons.unverify.title"
                            :model="entry"
                            swal-title="Remover verificação deste lançamento?"
                            label="verificado"
                            icon="fa fa-ban"
                            store="entries"
                            method="unverify"
                            :spinner-config="{ color: 'black' }"
                        >
                        </app-action-button>

                        <app-action-button
                            v-if="getEntryState(entry).buttons.analyse.visible"
                            :disabled="
                                getEntryState(entry).buttons.analyse.disabled
                            "
                            classes="btn btn-sm btn-micro btn-success"
                            :title="getEntryState(entry).buttons.analyse.title"
                            :model="entry"
                            swal-title="Analisar este lançamento?"
                            label="analisar"
                            icon="fa fa-check"
                            store="entries"
                            method="analyse"
                        >
                        </app-action-button>

                        <app-action-button
                            v-if="
                                getEntryState(entry).buttons.unanalyse.visible
                            "
                            :disabled="
                                getEntryState(entry).buttons.unanalyse.disabled
                            "
                            classes="btn btn-sm btn-micro btn-danger"
                            :title="
                                getEntryState(entry).buttons.unanalyse.title
                            "
                            :model="entry"
                            swal-title="Remover análise deste lançamento?"
                            label="analisado"
                            icon="fa fa-ban"
                            store="entries"
                            method="unanalyse"
                        >
                        </app-action-button>

                        <app-action-button
                            v-if="getEntryState(entry).buttons.publish.visible"
                            :disabled="
                                getEntryState(entry).buttons.publish.disabled
                            "
                            classes="btn btn-sm btn-micro btn-danger"
                            :title="getEntryState(entry).buttons.publish.title"
                            :model="entry"
                            swal-title="Publicar este lançamento?"
                            label="publicar"
                            icon="fa fa-check"
                            store="entries"
                            method="publish"
                        >
                        </app-action-button>

                        <app-action-button
                            v-if="
                                getEntryState(entry).buttons.unpublish.visible
                            "
                            :disabled="
                                getEntryState(entry).buttons.unpublish.disabled
                            "
                            classes="btn btn-sm btn-micro btn-danger"
                            :title="
                                getEntryState(entry).buttons.unpublish.title
                            "
                            :model="entry"
                            swal-title="Despublicar este lançamento?"
                            label="despublicar"
                            icon="fa fa-ban"
                            store="entries"
                            method="unpublish"
                        >
                        </app-action-button>

                        <button
                            v-if="getEntryState(entry).buttons.edit.visible"
                            :disabled="
                                getEntryState(entry).buttons.edit.disabled
                            "
                            class="btn btn-sm btn-micro btn-primary"
                            @click="editEntry(entry)"
                            :title="getEntryState(entry).buttons.edit.title"
                        >
                            <i class="fa fa-edit"></i>
                        </button>

                        <app-action-button
                            v-if="getEntryState(entry).buttons.delete.visible"
                            :disabled="
                                getEntryState(entry).buttons.delete.disabled
                            "
                            classes="btn btn-sm btn-micro btn-danger"
                            :title="getEntryState(entry).buttons.delete.title"
                            :model="entry"
                            swal-title="Deseja realmente deletar este lançamento?"
                            label=""
                            icon="fa fa-trash"
                            store="entries"
                            method="delete"
                            :spinner-config="{ size: '0.05em' }"
                            :swal-message="{ r200: 'Deletado com sucesso' }"
                        >
                        </app-action-button>
                    </div>
                </td>
            </tr>
        </app-table>

        <app-entry-form :show.sync="showModal"></app-entry-form>
    </app-table-panel>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import crud from '../../views/mixins/crud'
import entries from '../../views/mixins/entries'
import congressmen from '../../views/mixins/congressmen'
import permissions from '../../views/mixins/permissions'
import congressmanBudgets from '../../views/mixins/congressmanBudgets'

const service = {
    name: 'entries',

    uri:
        'congressmen/{congressmen.selected.id}/budgets/{congressmanBudgets.selected.id}/entries',
}

export default {
    mixins: [crud, entries, permissions, congressmanBudgets, congressmen],

    data() {
        return {
            service: service,

            showModal: false,
        }
    },

    methods: {
        ...mapActions(service.name, [
            'selectEntry',
            'clearForm',
            'clearErrors',
        ]),

        getEntryType(entry) {
            if (entry.cost_center_code == 2) {
                return {
                    name: 'transporte',
                    class:
                        entry.value > 0
                            ? 'badge badge-danger'
                            : 'badge badge-success',
                }
            } else if (entry.cost_center_code == 3) {
                return {
                    name: 'transporte',
                    class:
                        entry.value >= 0
                            ? 'badge badge-success'
                            : 'badge badge-danger',
                }
            } else if (entry.cost_center_code == 4) {
                return {
                    name: 'devolução',
                    class: 'badge badge-warning',
                }
            } else {
                if (entry.value > 0) {
                    return {
                        name: 'crédito',
                        class: 'badge badge-success',
                    }
                } else {
                    return {
                        name: 'débito',
                        class: 'badge badge-dark',
                    }
                }
            }
        },

        getTableColumns() {
            let columns = [
                'Data',
                'Objeto',
                'Favorecido',
                {
                    type: 'label',
                    title: 'Documentos',
                    trClass: 'text-right',
                },
            ]

            if (can('entry-comments:show')) {
                columns.push({
                    type: 'label',
                    title: 'Comentários',
                    trClass: 'text-right',
                })
            }

            columns.push({
                type: 'label',
                title: 'Valor',
                trClass: 'text-right',
            })

            if (can('entries:show')) {
                columns.push({
                    type: 'label',
                    title: 'Tipo',
                    trClass: 'text-center',
                })

                columns.push({
                    type: 'label',
                    title: 'Meio',
                    trClass: 'text-center',
                })

                columns.push({
                    type: 'label',
                    title: 'Pendências',
                    trClass: 'text-center',
                })

                columns.push({
                    type: 'label',
                    title: 'Verificado',
                    trClass: 'text-center',
                })

                columns.push({
                    type: 'label',
                    title: 'Analisado',
                    trClass: 'text-center',
                })

                columns.push({
                    type: 'label',
                    title: 'Publicidade',
                    trClass: 'text-center',
                })
            }

            columns.push('')

            return columns
        },

        createEntry() {
            if (filled(this.form.id)) {
                this.clearForm()
            }

            this.showModal = true
        },

        editEntry(entry) {
            this.showModal = true
        },
    },

    computed: {
        ...mapGetters({
            congressmanBudgetsSummaryLabel:
                'congressmanBudgets/currentSummaryLabel',
            congressmanBudgetsClosedAt: 'congressmanBudgets/selectedClosedAt',
            getEntryState: 'entries/getEntryState',
            selectedCongressmanBudgetState:
                'congressmanBudgets/getSelectedState',
            currentSummaryLabel: 'entries/currentSummaryLabel',
        }),
    },
}
</script>
