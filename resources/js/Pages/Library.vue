<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Library</h1>
        <p class="page-subtitle">Manage reusable checkpoints, checkpoint templates, and movement templates</p>
      </div>
      <div class="page-header-actions">
        <RefreshButton :only="['checkpoints', 'checkpointTemplates', 'movementTemplates']" />
        <Button v-if="activeTab === 'checkpoints'" variant="primary" size="sm" @click="showNewCheckpoint = true">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Checkpoint
        </Button>
        <Button v-if="activeTab === 'checkpoint-templates'" variant="primary" size="sm" @click="showNewCheckpointTemplate = true">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Checkpoint Template
        </Button>
        <Button v-if="activeTab === 'movement-templates'" variant="primary" size="sm" @click="showNewMovementTemplate = true">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Movement Template
        </Button>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display: flex; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
      <button 
        v-for="tab in tabs" 
        :key="tab.id" 
        @click="activeTab = tab.id"
        class="tab"
        :class="{ 'tab--active': activeTab === tab.id }"
      >
        {{ tab.label }}
        <span class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Checkpoints Tab -->
    <div v-if="activeTab === 'checkpoints'" style="flex: 1; overflow: auto;">
      <div class="plan-table-card">
        <div style="padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">Global Checkpoint Library</div>
            <!-- <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">{{ checkpoints.length }} Checkpoints</div> -->
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input 
              v-model="checkpointSearch" 
              type="text" 
              placeholder="Search checkpoints..." 
              style="padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; width: 200px;"
            />
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 120px 1.5fr 140px 140px 120px 120px; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); letter-spacing: 0.6px; text-transform: uppercase; position: sticky; top: 0; background: var(--surface);">
          <div>Code</div><div>Name</div><div>Type</div><div>Capture Method</div><div>Usage Count</div><div>Actions</div>
        </div>
        
        <div v-if="!filteredCheckpoints || filteredCheckpoints.length === 0" style="padding: 40px; text-align: center; color: var(--ink3);">
          <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">No checkpoints found</div>
          <div style="font-size: 12px;">Create your first checkpoint to get started</div>
        </div>
        
        <div 
          v-for="(checkpoint, i) in filteredCheckpoints" 
          :key="checkpoint.id"
          :style="{
            display: 'grid', 
            gridTemplateColumns: '120px 1.5fr 140px 140px 120px 120px', 
            gap: '10px',
            padding: '12px 14px',
            borderBottom: i === filteredCheckpoints.length - 1 ? 'none' : '1px solid var(--border)',
            alignItems: 'center',
            transition: 'background 0.13s',
          }"
          @mouseenter="$event.currentTarget.style.background = 'var(--panel)'"
          @mouseleave="$event.currentTarget.style.background = 'transparent'"
        >
          <div style="font-family: var(--mono); font-size: 11px; color: var(--ink); font-weight: 700;">{{ checkpoint.code }}</div>
          <div style="font-size: 13px; color: var(--ink); font-weight: 600;">{{ checkpoint.name }}</div>
          <div>
            <span :style="checkpointTypePillStyle(checkpoint.type)">{{ checkpoint.type.toUpperCase() }}</span>
          </div>
          <div style="font-size: 12px; color: var(--ink2);">{{ checkpoint.capture_method }}</div>
          <div style="font-size: 12px; color: var(--ink2); text-align: center;">{{ checkpoint.usage_count || 0 }}</div>
          <div style="display: flex; gap: 4px;">
            <TableActions 
              @edit="editCheckpoint(checkpoint)" 
              @delete="deleteCheckpoint(checkpoint.id)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Checkpoint Templates Tab -->
    <div v-if="activeTab === 'checkpoint-templates'" style="flex: 1; overflow: auto;">
      <div class="plan-table-card">
        <div style="padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">Checkpoint Templates</div>
            <!-- <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">{{ checkpointTemplates.length }} Templates</div> -->
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 100px 1fr 100px 2.5fr 100px 100px; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); letter-spacing: 0.6px; text-transform: uppercase; position: sticky; top: 0; background: var(--surface);">
          <div>Code</div><div>Name</div><div>Movement Type</div><div>Checkpoint Sequence</div><div>Est. Duration</div><div>Actions</div>
        </div>
        
        <div v-if="!checkpointTemplates || checkpointTemplates.length === 0" style="padding: 40px; text-align: center; color: var(--ink3);">
          <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">No checkpoint templates yet</div>
          <div style="font-size: 12px;">Create a template to define checkpoint sequences</div>
        </div>
        
        <div 
          v-for="(template, i) in checkpointTemplates" 
          :key="template.id"
          :style="{
            display: 'grid', 
            gridTemplateColumns: '100px 1fr 100px 2.5fr 100px 100px', 
            gap: '10px',
            padding: '12px 14px',
            borderBottom: i === checkpointTemplates.length - 1 ? 'none' : '1px solid var(--border)',
            alignItems: 'center',
            transition: 'background 0.13s',
          }"
          @mouseenter="$event.currentTarget.style.background = 'var(--panel)'"
          @mouseleave="$event.currentTarget.style.background = 'transparent'"
        >
          <div style="font-family: var(--mono); font-size: 11px; color: var(--ink); font-weight: 700;">{{ template.code }}</div>
          <div style="font-size: 13px; color: var(--ink); font-weight: 600;">{{ template.name }}</div>
          <div>
            <span :style="movementTypePillStyle(template.movement_type)">{{ template.movement_type }}</span>
          </div>
          <div style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
            <span 
              v-for="cp in template.checkpoints" 
              :key="cp.id"
              :style="{
                fontSize: '11px',
                fontWeight: '600',
                padding: '4px 10px',
                borderRadius: '4px',
                background: 'var(--panel)',
                border: '1px solid var(--border)',
                color: 'var(--ink)',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
                whiteSpace: 'nowrap',
              }"
              :title="cp.code + ' - ' + cp.type"
            >
              <span style="color: var(--ink3); font-weight: 700;">{{ cp.pivot.order }}.</span>
              <span>{{ cp.name }}</span>
            </span>
            <span v-if="!template.checkpoints || template.checkpoints.length === 0" style="font-size: 11px; color: var(--ink3); font-style: italic;">No checkpoints</span>
          </div>
          <div style="font-size: 12px; color: var(--ink2);">{{ template.estimated_duration_minutes || 0 }} min</div>
          <div style="display: flex; gap: 4px;">
            <TableActions 
              @edit="editCheckpointTemplate(template)" 
              @delete="deleteCheckpointTemplate(template.id)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Movement Templates Tab -->
    <div v-if="activeTab === 'movement-templates'" style="flex: 1; overflow: hidden; display: flex; gap: 12px;">
      <!-- Templates List -->
      <div class="plan-table-card" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <div style="padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">Movement Templates</div>
            <!-- <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">{{ movementTemplates.length }} Templates</div> -->
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 120px 1fr 100px 120px 100px; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); letter-spacing: 0.6px; text-transform: uppercase; background: var(--surface);">
          <div>Code</div><div>Name</div><div>Legs</div><div>Est. Duration</div><div>Actions</div>
        </div>
        
        <div v-if="!movementTemplates || movementTemplates.length === 0" style="padding: 40px; text-align: center; color: var(--ink3);">
          <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">No movement templates yet</div>
          <div style="font-size: 12px;">Create a template to define multi-leg itineraries</div>
        </div>
        
        <div style="overflow: auto; flex: 1;">
          <div 
            v-for="(template, i) in movementTemplates" 
            :key="template.id"
            :style="{
              display: 'grid', 
              gridTemplateColumns: '120px 1fr 100px 120px 100px', 
              gap: '10px',
              padding: '12px 14px',
              borderBottom: i === movementTemplates.length - 1 ? 'none' : '1px solid var(--border)',
              alignItems: 'center',
              cursor: 'pointer',
              transition: 'background 0.13s',
              borderLeft: selectedMovementTemplate?.id === template.id ? '3px solid var(--accent)' : '3px solid transparent',
              background: selectedMovementTemplate?.id === template.id ? 'var(--accent-soft)' : 'transparent',
            }"
            @click="selectMovementTemplate(template)"
            @mouseenter="selectedMovementTemplate?.id !== template.id && ($event.currentTarget.style.background = 'var(--panel)')"
            @mouseleave="selectedMovementTemplate?.id !== template.id && ($event.currentTarget.style.background = 'transparent')"
          >
            <div style="font-family: var(--mono); font-size: 11px; color: var(--ink); font-weight: 700;">{{ template.code }}</div>
            <div>
              <div style="font-size: 13px; color: var(--ink); font-weight: 600; margin-bottom: 2px;">{{ template.name }}</div>
              <div style="font-size: 11px; color: var(--ink3);">
                {{ template.scenario_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
              </div>
            </div>
            <div style="font-size: 12px; color: var(--ink2); text-align: center;">{{ template.total_legs || 0 }}</div>
            <div style="font-size: 12px; color: var(--ink2);">{{ template.estimated_duration_minutes || 0 }} min</div>
            <div style="display: flex; gap: 4px;" @click.stop>
              <TableActions 
                @edit="editMovementTemplate(template)" 
                @delete="deleteMovementTemplate(template.id)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Template Detail Card -->
      <transition name="slide-left">
        <div v-if="selectedMovementTemplate" style="width: 500px; display: flex; flex-direction: column; gap: 12px; overflow: auto;">
          <!-- Header Card -->
          <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
              <div style="flex: 1;">
                <div style="font-family: var(--mono); font-size: 11px; color: var(--ink3); font-weight: 600; margin-bottom: 4px;">{{ selectedMovementTemplate.code }}</div>
                <div style="font-size: 16px; font-weight: 700; color: var(--ink); margin-bottom: 4px;">{{ selectedMovementTemplate.name }}</div>
                <div style="font-size: 12px; color: var(--ink3);">
                  {{ selectedMovementTemplate.scenario_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                  <span v-if="selectedMovementTemplate.estimated_duration_minutes"> · {{ selectedMovementTemplate.estimated_duration_minutes }} min</span>
                </div>
              </div>
              <button @click="selectedMovementTemplate = null" style="padding: 4px; border: none; background: transparent; cursor: pointer; color: var(--ink3); display: flex; align-items: center; justify-content: center; border-radius: 4px;" onmouseover="this.style.background='var(--panel)'" onmouseout="this.style.background='transparent'">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M15 5L5 15M5 5l10 10" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
            
            <div v-if="selectedMovementTemplate.description" style="font-size: 12px; color: var(--ink2); padding: 10px; background: var(--panel); border-radius: 6px; margin-bottom: 12px;">
              {{ selectedMovementTemplate.description }}
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
              <div style="padding: 10px; background: var(--panel); border-radius: 6px;">
                <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Total Legs</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink);">{{ selectedMovementTemplate.total_legs || 0 }}</div>
              </div>
              <div style="padding: 10px; background: var(--panel); border-radius: 6px;">
                <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Est. Duration</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink);">{{ selectedMovementTemplate.estimated_duration_minutes || 0 }} min</div>
              </div>
            </div>
          </div>

          <!-- Legs & Checkpoints Card -->
          <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; flex: 1; display: flex; flex-direction: column;">
            <div style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
              <div style="font-size: 14px; font-weight: 700; color: var(--ink);">Movement Legs</div>
              <div style="font-size: 11px; color: var(--ink3); margin-top: 2px;">Route and checkpoints</div>
            </div>
            
            <div v-if="!selectedMovementTemplate.legs || selectedMovementTemplate.legs.length === 0" style="padding: 40px; text-align: center; color: var(--ink3);">
              <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">No legs defined</div>
              <div style="font-size: 11px;">Add legs to this template</div>
            </div>
            
            <div v-else style="overflow: auto; flex: 1;">
              <div v-for="(leg, idx) in selectedMovementTemplate.legs" :key="idx" style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
                <!-- Leg Header -->
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px;">
                  <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;">{{ leg.order }}</div>
                  <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                      <span style="font-size: 13px; font-weight: 700; color: var(--ink);">
                        {{ leg.from_location || 'Start' }} → {{ leg.to_location || 'End' }}
                      </span>
                      <span v-if="leg.leg_type" :style="movementTypePillStyle(leg.leg_type)">{{ leg.leg_type }}</span>
                    </div>
                    <div style="font-size: 11px; color: var(--ink3); display: flex; align-items: center; gap: 8px;">
                      <span style="text-transform: capitalize;">{{ leg.transport_type }}</span>
                      <span v-if="leg.estimated_duration_minutes">• {{ leg.estimated_duration_minutes }} min</span>
                    </div>
                  </div>
                </div>
                
                <!-- Checkpoint Template -->
                <div v-if="getCheckpointTemplateById(leg.checkpoint_template_id)" style="padding: 10px; background: var(--panel); border-radius: 6px; margin-left: 38px;">
                  <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Checkpoint Template</div>
                  <div style="font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 4px;">
                    {{ getCheckpointTemplateById(leg.checkpoint_template_id).name }}
                  </div>
                  <div style="font-size: 11px; font-family: var(--mono); color: var(--ink3); margin-bottom: 8px;">
                    {{ getCheckpointTemplateById(leg.checkpoint_template_id).code }}
                  </div>
                  
                  <!-- Checkpoints List -->
                  <div v-if="getCheckpointTemplateById(leg.checkpoint_template_id).checkpoints && getCheckpointTemplateById(leg.checkpoint_template_id).checkpoints.length > 0" style="margin-top: 10px;">
                    <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Checkpoints ({{ getCheckpointTemplateById(leg.checkpoint_template_id).checkpoints.length }})</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                      <div v-for="cp in getCheckpointTemplateById(leg.checkpoint_template_id).checkpoints" :key="cp.id" style="font-size: 10px; font-weight: 600; padding: 4px 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; color: var(--ink2);">
                        {{ cp.pivot.order }}. {{ cp.name }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- New Checkpoint Modal -->
    <Modal :show="showNewCheckpoint" @close="closeNewCheckpointModal">
      <template #title>New Checkpoint</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="newCheckpoint.name" type="text" placeholder="Dispatch from depot" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Type</label>
          <select v-model="newCheckpoint.type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="dispatch">Dispatch</option>
            <option value="arrival">Arrival</option>
            <option value="boarding">Boarding</option>
            <option value="departure">Departure</option>
            <option value="handoff">Handoff</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Capture Method</label>
          <select v-model="newCheckpoint.capture_method" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="manual">Manual</option>
            <option value="auto">Auto</option>
            <option value="gps">GPS</option>
            <option value="photo">Photo</option>
            <option value="signature">Signature</option>
          </select>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <input v-model="newCheckpoint.requires_photo" type="checkbox" id="new-requires-photo" style="width: 16px; height: 16px;" />
          <label for="new-requires-photo" style="font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer;">Requires Photo</label>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <input v-model="newCheckpoint.requires_signature" type="checkbox" id="new-requires-signature" style="width: 16px; height: 16px;" />
          <label for="new-requires-signature" style="font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer;">Requires Signature</label>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(checkpointErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in checkpointErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeNewCheckpointModal" :disabled="processingCheckpoint">Cancel</Button>
            <Button variant="primary" size="sm" @click="createCheckpoint" :disabled="processingCheckpoint">
              {{ processingCheckpoint ? 'Creating...' : 'Create' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Edit Checkpoint Modal -->
    <Modal v-if="editingCheckpoint" :show="showEditCheckpoint" @close="closeEditCheckpointModal">
      <template #title>Edit Checkpoint</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Code</label>
          <input v-model="editingCheckpoint.code" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="editingCheckpoint.name" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Type</label>
          <select v-model="editingCheckpoint.type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="dispatch">Dispatch</option>
            <option value="arrival">Arrival</option>
            <option value="boarding">Boarding</option>
            <option value="departure">Departure</option>
            <option value="handoff">Handoff</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Capture Method</label>
          <select v-model="editingCheckpoint.capture_method" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="manual">Manual</option>
            <option value="auto">Auto</option>
            <option value="gps">GPS</option>
            <option value="photo">Photo</option>
            <option value="signature">Signature</option>
          </select>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <input v-model="editingCheckpoint.requires_photo" type="checkbox" id="edit-requires-photo" style="width: 16px; height: 16px;" />
          <label for="edit-requires-photo" style="font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer;">Requires Photo</label>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <input v-model="editingCheckpoint.requires_signature" type="checkbox" id="edit-requires-signature" style="width: 16px; height: 16px;" />
          <label for="edit-requires-signature" style="font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer;">Requires Signature</label>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(checkpointErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in checkpointErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeEditCheckpointModal" :disabled="processingCheckpoint">Cancel</Button>
            <Button variant="primary" size="sm" @click="updateCheckpoint" :disabled="processingCheckpoint">
              {{ processingCheckpoint ? 'Updating...' : 'Update' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- New Checkpoint Template Modal -->
    <Modal :show="showNewCheckpointTemplate" @close="closeNewCheckpointTemplateModal" maxWidth="800px">
      <template #title>New Checkpoint Template</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Code</label>
          <input v-model="newCheckpointTemplate.code" type="text" placeholder="TMPL-ARR" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="newCheckpointTemplate.name" type="text" placeholder="Standard Arrival" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Movement Type</label>
          <select v-model="newCheckpointTemplate.movement_type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="arrival">Arrival</option>
            <option value="departure">Departure</option>
            <option value="transfer">Transfer</option>
            <option value="training">Training</option>
            <option value="match">Match</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Description</label>
          <textarea v-model="newCheckpointTemplate.description" placeholder="Optional description" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Estimated Duration (minutes)</label>
          <input v-model.number="newCheckpointTemplate.estimated_duration_minutes" type="number" min="1" placeholder="30" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        
        <!-- Checkpoint Builder -->
        <div style="margin-top: 8px;">
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--ink);">Checkpoints</label>
          
          <!-- Add Checkpoint Section -->
          <div style="display: flex; gap: 8px; margin-bottom: 12px;">
            <select v-model="selectedCheckpointId" style="flex: 1; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
              <option :value="null">Select checkpoint to add...</option>
              <option v-for="checkpoint in props.checkpoints" :key="checkpoint.id" :value="checkpoint.id" :disabled="newCheckpointTemplate.checkpoints.some(c => c.checkpoint_id === checkpoint.id)">
                {{ checkpoint.code }} - {{ checkpoint.name }}
              </option>
            </select>
            <Button variant="secondary" size="sm" @click="addCheckpointToTemplate" :disabled="!selectedCheckpointId">
              <template #icon><svg-icon name="plus" :size="14" /></template>
              Add
            </Button>
          </div>
          
          <!-- Selected Checkpoints List -->
          <div v-if="newCheckpointTemplate.checkpoints.length > 0" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
            <div style="background: var(--panel); padding: 8px 10px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">
              {{ newCheckpointTemplate.checkpoints.length }} Checkpoint{{ newCheckpointTemplate.checkpoints.length !== 1 ? 's' : '' }}
            </div>
            <div v-for="(cp, index) in newCheckpointTemplate.checkpoints" :key="index" style="padding: 10px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                  <button @click="moveCheckpointUp(index)" :disabled="index === 0" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === 0 ? 0.3 : 1 }">
                    ▲
                  </button>
                  <button @click="moveCheckpointDown(index)" :disabled="index === newCheckpointTemplate.checkpoints.length - 1" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === newCheckpointTemplate.checkpoints.length - 1 ? 0.3 : 1 }">
                    ▼
                  </button>
                </div>
                <div style="flex: 1;">
                  <div style="font-size: 13px; font-weight: 600; color: var(--ink);">{{ cp.order }}. {{ getCheckpointName(cp.checkpoint_id) }}</div>
                  <div style="font-size: 11px; color: var(--ink3); font-family: monospace; margin-top: 2px;">{{ getCheckpointCode(cp.checkpoint_id) }}</div>
                </div>
                <button @click="removeCheckpointFromTemplate(index)" style="padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; color: #DC2626; font-size: 12px; font-weight: 600;">
                  Remove
                </button>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <input v-model="cp.is_required" type="checkbox" :id="`checkpoint-${index}-required`" style="width: 14px; height: 14px;" />
                  <label :for="`checkpoint-${index}-required`" style="font-size: 12px; color: var(--ink2); cursor: pointer;">Required</label>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label :for="`checkpoint-${index}-minutes`" style="font-size: 12px; color: var(--ink2); white-space: nowrap;">Est. mins:</label>
                  <input v-model.number="cp.estimated_minutes" type="number" min="1" :id="`checkpoint-${index}-minutes`" style="width: 60px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
                </div>
              </div>
            </div>
          </div>
          <div v-else style="padding: 20px; text-align: center; color: var(--ink3); background: var(--panel); border: 1px dashed var(--border); border-radius: 6px;">
            <div style="font-size: 12px;">No checkpoints added yet</div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(checkpointTemplateErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in checkpointTemplateErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeNewCheckpointTemplateModal" :disabled="processingCheckpointTemplate">Cancel</Button>
            <Button variant="primary" size="sm" @click="createCheckpointTemplate" :disabled="processingCheckpointTemplate">
              {{ processingCheckpointTemplate ? 'Creating...' : 'Create' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Edit Checkpoint Template Modal -->
    <Modal v-if="editingCheckpointTemplate" :show="showEditCheckpointTemplate" @close="closeEditCheckpointTemplateModal" maxWidth="800px">
      <template #title>Edit Checkpoint Template</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Code</label>
          <input v-model="editingCheckpointTemplate.code" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="editingCheckpointTemplate.name" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Movement Type</label>
          <select v-model="editingCheckpointTemplate.movement_type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="arrival">Arrival</option>
            <option value="departure">Departure</option>
            <option value="transfer">Transfer</option>
            <option value="training">Training</option>
            <option value="match">Match</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Description</label>
          <textarea v-model="editingCheckpointTemplate.description" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Estimated Duration (minutes)</label>
          <input v-model.number="editingCheckpointTemplate.estimated_duration_minutes" type="number" min="1" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        
        <!-- Checkpoint Builder -->
        <div style="margin-top: 8px;">
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--ink);">Checkpoints</label>
          <div style="display: flex; gap: 8px; margin-bottom: 12px;">
            <select v-model="selectedCheckpointIdEdit" style="flex: 1; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
              <option :value="null">Select a checkpoint...</option>
              <option 
                v-for="checkpoint in checkpoints" 
                :key="checkpoint.id" 
                :value="checkpoint.id"
                :disabled="editingCheckpointTemplate.checkpoints?.some(c => c.checkpoint_id === checkpoint.id)"
              >
                {{ checkpoint.code }} - {{ checkpoint.name }}
              </option>
            </select>
            <Button variant="primary" size="sm" @click="addCheckpointToEditTemplate" :disabled="!selectedCheckpointIdEdit">Add</Button>
          </div>
          <div v-if="editingCheckpointTemplate.checkpoints && editingCheckpointTemplate.checkpoints.length > 0" style="display: flex; flex-direction: column; gap: 8px;">
            <div 
              v-for="(cp, index) in editingCheckpointTemplate.checkpoints" 
              :key="index"
              style="padding: 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--panel);"
            >
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                  <div style="font-size: 11px; font-weight: 700; color: var(--ink3); min-width: 24px;">{{ cp.order }}.</div>
                  <div style="font-family: var(--mono); font-size: 11px; color: var(--ink3); font-weight: 700;">{{ getCheckpointCode(cp.checkpoint_id) }}</div>
                  <div style="font-size: 12px; color: var(--ink); font-weight: 600;">{{ getCheckpointName(cp.checkpoint_id) }}</div>
                </div>
                <div style="display: flex; gap: 4px;">
                  <button 
                    @click="moveCheckpointUpEdit(index)" 
                    :disabled="index === 0"
                    style="padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; display: flex; align-items: center; justify-content: center;"
                    :style="{ opacity: index === 0 ? 0.5 : 1, cursor: index === 0 ? 'not-allowed' : 'pointer' }"
                  >
                    ↑
                  </button>
                  <button 
                    @click="moveCheckpointDownEdit(index)" 
                    :disabled="index === editingCheckpointTemplate.checkpoints.length - 1"
                    style="padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; display: flex; align-items: center; justify-content: center;"
                    :style="{ opacity: index === editingCheckpointTemplate.checkpoints.length - 1 ? 0.5 : 1, cursor: index === editingCheckpointTemplate.checkpoints.length - 1 ? 'not-allowed' : 'pointer' }"
                  >
                    ↓
                  </button>
                  <button 
                    @click="removeCheckpointFromEditTemplate(index)"
                    style="padding: 4px 8px; border: 1px solid #FCA5A5; border-radius: 4px; background: #FEE2E2; color: #991B1B; cursor: pointer; font-size: 11px; font-weight: 600;"
                  >
                    Remove
                  </button>
                </div>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <input v-model="cp.is_required" type="checkbox" :id="`edit-checkpoint-${index}-required`" style="width: 14px; height: 14px;" />
                  <label :for="`edit-checkpoint-${index}-required`" style="font-size: 12px; color: var(--ink2); cursor: pointer;">Required</label>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label :for="`edit-checkpoint-${index}-minutes`" style="font-size: 12px; color: var(--ink2); white-space: nowrap;">Est. mins:</label>
                  <input v-model.number="cp.estimated_minutes" type="number" min="1" :id="`edit-checkpoint-${index}-minutes`" style="width: 60px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
                </div>
              </div>
            </div>
          </div>
          <div v-else style="padding: 20px; text-align: center; color: var(--ink3); background: var(--panel); border: 1px dashed var(--border); border-radius: 6px;">
            <div style="font-size: 12px;">No checkpoints added yet</div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(checkpointTemplateErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in checkpointTemplateErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeEditCheckpointTemplateModal" :disabled="processingCheckpointTemplate">Cancel</Button>
            <Button variant="primary" size="sm" @click="updateCheckpointTemplate" :disabled="processingCheckpointTemplate">
              {{ processingCheckpointTemplate ? 'Updating...' : 'Update' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- New Movement Template Modal -->
    <Modal :show="showNewMovementTemplate" @close="closeNewMovementTemplateModal" maxWidth="900px">
      <template #title>New Movement Template</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Code</label>
          <input v-model="newMovementTemplate.code" type="text" placeholder="MVMT-MD" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="newMovementTemplate.name" type="text" placeholder="Match Day Standard" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Scenario Type</label>
          <select v-model="newMovementTemplate.scenario_type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="match_day">Match Day</option>
            <option value="training_day">Training Day</option>
            <option value="arrival_day">Arrival Day</option>
            <option value="departure_day">Departure Day</option>
            <option value="full_day">Full Day</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Description</label>
          <textarea v-model="newMovementTemplate.description" placeholder="Optional description" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Estimated Duration (minutes)</label>
          <input v-model.number="newMovementTemplate.estimated_duration_minutes" type="number" min="1" placeholder="180" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        
        <!-- Leg Builder -->
        <div style="margin-top: 8px;">
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--ink);">Movement Legs</label>
          
          <!-- Add Leg Form -->
          <div style="border: 1px solid var(--border); border-radius: 6px; padding: 12px; background: var(--panel); margin-bottom: 12px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">From Location</label>
                <input v-model="newLeg.from_location" type="text" placeholder="Team Hotel" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">To Location</label>
                <input v-model="newLeg.to_location" type="text" placeholder="Stadium" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Checkpoint Template</label>
                <select v-model="selectedCheckpointTemplateId" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option :value="null">Select template...</option>
                  <option v-for="template in checkpointTemplates" :key="template.id" :value="template.id">
                    {{ template.code }} - {{ template.name }}
                  </option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Phase</label>
                <select v-model="newLeg.leg_type" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option value="transfer">Transfer</option>
                  <option value="arrival">Arrival</option>
                  <option value="training">Training</option>
                  <option value="departure">Departure</option>
                  <option value="match">Match</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Transport</label>
                <select v-model="newLeg.transport_type" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option value="bus">Bus</option>
                  <option value="walk">Walk</option>
                  <option value="car">Car</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Est. mins</label>
                <input v-model.number="newLeg.estimated_duration_minutes" type="number" min="1" placeholder="30" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
            </div>
            <Button variant="secondary" size="sm" @click="addLegToTemplate" :disabled="!selectedCheckpointTemplateId || !newLeg.from_location || !newLeg.to_location" style="width: 100%;">
              <template #icon><svg-icon name="plus" :size="14" /></template>
              Add Leg
            </Button>
          </div>
          
          <!-- Legs List -->
          <div v-if="newMovementTemplate.legs.length > 0" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
            <div style="background: var(--panel); padding: 8px 10px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">
              {{ newMovementTemplate.legs.length }} Leg{{ newMovementTemplate.legs.length !== 1 ? 's' : '' }}
            </div>
            <div v-for="(leg, index) in newMovementTemplate.legs" :key="index" style="padding: 10px; border-bottom: 1px solid var(--border);">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                  <button @click="moveLegUp(index)" :disabled="index === 0" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === 0 ? 0.3 : 1 }">
                    ▲
                  </button>
                  <button @click="moveLegDown(index)" :disabled="index === newMovementTemplate.legs.length - 1" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === newMovementTemplate.legs.length - 1 ? 0.3 : 1 }">
                    ▼
                  </button>
                </div>
                <div style="flex: 1;">
                  <div style="font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 4px;">
                    {{ leg.order }}. {{ leg.from_location }} → {{ leg.to_location }}
                  </div>
                  <div style="display: flex; gap: 8px; font-size: 11px; color: var(--ink3);">
                    <span style="font-family: var(--mono);">{{ getCheckpointTemplateCode(leg.checkpoint_template_id) }}</span>
                    <span>•</span>
                    <span>{{ leg.transport_type }}</span>
                    <span v-if="leg.estimated_duration_minutes">• {{ leg.estimated_duration_minutes }} min</span>
                  </div>
                </div>
                <button @click="removeLegFromTemplate(index)" style="padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; color: #DC2626; font-size: 12px; font-weight: 600;">
                  Remove
                </button>
              </div>
            </div>
          </div>
          <div v-else style="padding: 20px; text-align: center; color: var(--ink3); background: var(--panel); border: 1px dashed var(--border); border-radius: 6px;">
            <div style="font-size: 12px;">No legs added yet</div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(movementTemplateErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in movementTemplateErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeNewMovementTemplateModal" :disabled="processingMovementTemplate">Cancel</Button>
            <Button variant="primary" size="sm" @click="createMovementTemplate" :disabled="processingMovementTemplate">
              {{ processingMovementTemplate ? 'Creating...' : 'Create' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Edit Movement Template Modal -->
    <Modal v-if="editingMovementTemplate" :show="showEditMovementTemplate" @close="closeEditMovementTemplateModal" maxWidth="900px">
      <template #title>Edit Movement Template</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Code</label>
          <input v-model="editingMovementTemplate.code" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Name</label>
          <input v-model="editingMovementTemplate.name" type="text" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Scenario Type</label>
          <select v-model="editingMovementTemplate.scenario_type" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option value="match_day">Match Day</option>
            <option value="training_day">Training Day</option>
            <option value="arrival_day">Arrival Day</option>
            <option value="departure_day">Departure Day</option>
            <option value="full_day">Full Day</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Description</label>
          <textarea v-model="editingMovementTemplate.description" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Estimated Duration (minutes)</label>
          <input v-model.number="editingMovementTemplate.estimated_duration_minutes" type="number" min="1" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;" />
        </div>
        
        <!-- Leg Builder -->
        <div style="margin-top: 8px;">
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--ink);">Movement Legs</label>
          
          <!-- Add Leg Form -->
          <div style="border: 1px solid var(--border); border-radius: 6px; padding: 12px; background: var(--panel); margin-bottom: 12px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">From Location</label>
                <input v-model="newLegEdit.from_location" type="text" placeholder="Team Hotel" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">To Location</label>
                <input v-model="newLegEdit.to_location" type="text" placeholder="Stadium" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Checkpoint Template</label>
                <select v-model="selectedCheckpointTemplateIdEdit" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option :value="null">Select template...</option>
                  <option v-for="template in checkpointTemplates" :key="template.id" :value="template.id">
                    {{ template.code }} - {{ template.name }}
                  </option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Phase</label>
                <select v-model="newLegEdit.leg_type" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option value="transfer">Transfer</option>
                  <option value="arrival">Arrival</option>
                  <option value="training">Training</option>
                  <option value="departure">Departure</option>
                  <option value="match">Match</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Transport</label>
                <select v-model="newLegEdit.transport_type" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">
                  <option value="bus">Bus</option>
                  <option value="walk">Walk</option>
                  <option value="car">Car</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: var(--ink2);">Est. mins</label>
                <input v-model.number="newLegEdit.estimated_duration_minutes" type="number" min="1" placeholder="30" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px;" />
              </div>
            </div>
            <Button variant="secondary" size="sm" @click="addLegToEditTemplate" :disabled="!selectedCheckpointTemplateIdEdit || !newLegEdit.from_location || !newLegEdit.to_location" style="width: 100%;">
              <template #icon><svg-icon name="plus" :size="14" /></template>
              Add Leg
            </Button>
          </div>
          
          <!-- Legs List -->
          <div v-if="editingMovementTemplate.legs && editingMovementTemplate.legs.length > 0" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
            <div style="background: var(--panel); padding: 8px 10px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">
              {{ editingMovementTemplate.legs.length }} Leg{{ editingMovementTemplate.legs.length !== 1 ? 's' : '' }}
            </div>
            <div v-for="(leg, index) in editingMovementTemplate.legs" :key="index" style="padding: 10px; border-bottom: 1px solid var(--border);">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                  <button @click="moveLegUpEdit(index)" :disabled="index === 0" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === 0 ? 0.3 : 1 }">
                    ▲
                  </button>
                  <button @click="moveLegDownEdit(index)" :disabled="index === editingMovementTemplate.legs.length - 1" style="padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 10px;" :style="{ opacity: index === editingMovementTemplate.legs.length - 1 ? 0.3 : 1 }">
                    ▼
                  </button>
                </div>
                <div style="flex: 1;">
                  <div style="font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 4px;">
                    {{ leg.order }}. {{ leg.from_location }} → {{ leg.to_location }}
                  </div>
                  <div style="display: flex; gap: 8px; font-size: 11px; color: var(--ink3);">
                    <span style="font-family: var(--mono);">{{ getCheckpointTemplateCode(leg.checkpoint_template_id) }}</span>
                    <span>•</span>
                    <span>{{ leg.transport_type }}</span>
                    <span v-if="leg.estimated_duration_minutes">• {{ leg.estimated_duration_minutes }} min</span>
                  </div>
                </div>
                <button @click="removeLegFromEditTemplate(index)" style="padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); cursor: pointer; color: #DC2626; font-size: 12px; font-weight: 600;">
                  Remove
                </button>
              </div>
            </div>
          </div>
          <div v-else style="padding: 20px; text-align: center; color: var(--ink3); background: var(--panel); border: 1px dashed var(--border); border-radius: 6px;">
            <div style="font-size: 12px;">No legs added yet</div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div v-if="Object.keys(movementTemplateErrors).length > 0" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
            <div v-for="(messages, field) in movementTemplateErrors" :key="field">
              <div v-if="Array.isArray(messages)">
                <div v-for="(message, index) in messages" :key="index">{{ message }}</div>
              </div>
              <div v-else>{{ messages }}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="closeEditMovementTemplateModal" :disabled="processingMovementTemplate">Cancel</Button>
            <Button variant="primary" size="sm" @click="updateMovementTemplate" :disabled="processingMovementTemplate">
              {{ processingMovementTemplate ? 'Updating...' : 'Update' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-if="deleteTarget" :show="showDeleteConfirmation" @close="cancelDelete">
      <template #title>
        <div style="display: flex; align-items: center; gap: 8px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #DC2626;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <span>Delete {{ deleteTarget.entityType }}</span>
        </div>
      </template>
      <div style="padding: 4px 0;">
        <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px;">
          Are you sure you want to delete <strong>{{ deleteTarget.name }}</strong>?
        </p>
        <p style="font-size: 13px; color: var(--ink2);">
          This action cannot be undone.
        </p>
      </div>
      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="cancelDelete" :disabled="processingDelete">Cancel</Button>
          <Button variant="primary" size="sm" @click="confirmDelete" :disabled="processingDelete" style="background: #DC2626; border-color: #DC2626;">
            {{ processingDelete ? 'Deleting...' : 'Delete' }}
          </Button>
        </div>
      </template>
    </Modal>

  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Button from '../Components/Button.vue';
import Modal from '../Components/Modal.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import TableActions from '../Components/TableActions.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import { useToast } from '../Composables/useToast';

const { error: showErrorToast, success: showSuccessToast } = useToast();

// Props from backend
const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  checkpointTemplates: { type: Array, default: () => [] },
  movementTemplates: { type: Array, default: () => [] },
});

// Active tab
const activeTab = ref('checkpoints');

// Tabs configuration
const tabs = computed(() => [
  { id: 'checkpoints', label: 'Checkpoints', count: props.checkpoints.length },
  { id: 'checkpoint-templates', label: 'Checkpoint Templates', count: props.checkpointTemplates.length },
  { id: 'movement-templates', label: 'Movement Templates', count: props.movementTemplates.length },
]);

// Checkpoint search
const checkpointSearch = ref('');
const filteredCheckpoints = computed(() => {
  if (!checkpointSearch.value) return props.checkpoints;
  const search = checkpointSearch.value.toLowerCase();
  return props.checkpoints.filter(c => 
    c.code.toLowerCase().includes(search) || 
    c.name.toLowerCase().includes(search) ||
    c.type.toLowerCase().includes(search)
  );
});

// Modals
const showNewCheckpoint = ref(false);
const showNewCheckpointTemplate = ref(false);
const showNewMovementTemplate = ref(false);
const showEditCheckpoint = ref(false);
const showEditCheckpointTemplate = ref(false);
const showEditMovementTemplate = ref(false);
const showDeleteConfirmation = ref(false);

// Processing states
const processingCheckpoint = ref(false);
const processingCheckpointTemplate = ref(false);
const processingMovementTemplate = ref(false);
const processingDelete = ref(false);

// Validation errors
const checkpointErrors = ref({});
const checkpointTemplateErrors = ref({});
const movementTemplateErrors = ref({});

// Delete confirmation state
const deleteTarget = ref(null); // { type: 'checkpoint'|'checkpoint-template'|'movement-template', id: number, name: string }

// New checkpoint form
const newCheckpoint = ref({
  name: '',
  type: 'dispatch',
  capture_method: 'manual',
  requires_photo: false,
  requires_signature: false,
  is_active: true,
});

// Edit checkpoint form
const editingCheckpoint = ref(null);

// New checkpoint template form
const newCheckpointTemplate = ref({
  code: '',
  name: '',
  movement_type: 'arrival',
  description: '',
  estimated_duration_minutes: null,
  is_active: true,
  checkpoints: [], // Array of { checkpoint_id, order, is_required, estimated_minutes }
});


// Checkpoint template builder state
const selectedCheckpointId = ref(null);

// Edit checkpoint template form
const editingCheckpointTemplate = ref(null);
const selectedCheckpointIdEdit = ref(null);

// New movement template form
const newMovementTemplate = ref({
  code: '',
  name: '',
  scenario_type: 'match_day',
  description: '',
  estimated_duration_minutes: null,
  is_active: true,
  legs: [], // Array of { order, from_location, to_location, checkpoint_template_id, estimated_duration_minutes, transport_type }
});

// Movement template leg builder state
const selectedCheckpointTemplateId = ref(null);
const newLeg = ref({
  from_location: '',
  to_location: '',
  leg_type: 'transfer',
  transport_type: 'bus',
  estimated_duration_minutes: null,
});

// Edit movement template form
const editingMovementTemplate = ref(null);
const selectedCheckpointTemplateIdEdit = ref(null);
const newLegEdit = ref({
  from_location: '',
  to_location: '',
  leg_type: 'transfer',
  transport_type: 'bus',
  estimated_duration_minutes: null,
});

// Selected movement template for detail view
const selectedMovementTemplate = ref(null);

// Clear errors when modals are closed
function closeNewCheckpointModal() {
  showNewCheckpoint.value = false;
  checkpointErrors.value = {};
}

function closeEditCheckpointModal() {
  showEditCheckpoint.value = false;
  checkpointErrors.value = {};
}

function closeNewCheckpointTemplateModal() {
  showNewCheckpointTemplate.value = false;
  checkpointTemplateErrors.value = {};
  selectedCheckpointId.value = null;
}

// Checkpoint template builder functions
function addCheckpointToTemplate() {
  if (!selectedCheckpointId.value) return;
  
  // Check if already added
  if (newCheckpointTemplate.value.checkpoints.some(c => c.checkpoint_id === selectedCheckpointId.value)) {
    return;
  }
  
  const nextOrder = newCheckpointTemplate.value.checkpoints.length + 1;
  newCheckpointTemplate.value.checkpoints.push({
    checkpoint_id: selectedCheckpointId.value,
    order: nextOrder,
    is_required: true,
    estimated_minutes: 5,
  });
  
  selectedCheckpointId.value = null;
}

function removeCheckpointFromTemplate(index) {
  newCheckpointTemplate.value.checkpoints.splice(index, 1);
  // Re-order remaining checkpoints
  newCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function moveCheckpointUp(index) {
  if (index === 0) return;
  const temp = newCheckpointTemplate.value.checkpoints[index];
  newCheckpointTemplate.value.checkpoints[index] = newCheckpointTemplate.value.checkpoints[index - 1];
  newCheckpointTemplate.value.checkpoints[index - 1] = temp;
  // Update orders
  newCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function moveCheckpointDown(index) {
  if (index === newCheckpointTemplate.value.checkpoints.length - 1) return;
  const temp = newCheckpointTemplate.value.checkpoints[index];
  newCheckpointTemplate.value.checkpoints[index] = newCheckpointTemplate.value.checkpoints[index + 1];
  newCheckpointTemplate.value.checkpoints[index + 1] = temp;
  // Update orders
  newCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function getCheckpointName(checkpointId) {
  const checkpoint = props.checkpoints.find(c => c.id === checkpointId);
  return checkpoint ? checkpoint.name : 'Unknown';
}

function getCheckpointCode(checkpointId) {
  const checkpoint = props.checkpoints.find(c => c.id === checkpointId);
  return checkpoint ? checkpoint.code : '';
}

function closeEditCheckpointTemplateModal() {
  showEditCheckpointTemplate.value = false;
  checkpointTemplateErrors.value = {};
  selectedCheckpointIdEdit.value = null;
}

// Checkpoint template builder functions for editing
function addCheckpointToEditTemplate() {
  if (!selectedCheckpointIdEdit.value) return;
  
  // Check if already added
  if (editingCheckpointTemplate.value.checkpoints.some(c => c.checkpoint_id === selectedCheckpointIdEdit.value)) {
    return;
  }
  
  const nextOrder = editingCheckpointTemplate.value.checkpoints.length + 1;
  editingCheckpointTemplate.value.checkpoints.push({
    checkpoint_id: selectedCheckpointIdEdit.value,
    order: nextOrder,
    is_required: true,
    estimated_minutes: 5,
  });
  
  selectedCheckpointIdEdit.value = null;
}

function removeCheckpointFromEditTemplate(index) {
  editingCheckpointTemplate.value.checkpoints.splice(index, 1);
  // Re-order remaining checkpoints
  editingCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function moveCheckpointUpEdit(index) {
  if (index === 0) return;
  const temp = editingCheckpointTemplate.value.checkpoints[index];
  editingCheckpointTemplate.value.checkpoints[index] = editingCheckpointTemplate.value.checkpoints[index - 1];
  editingCheckpointTemplate.value.checkpoints[index - 1] = temp;
  // Update orders
  editingCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function moveCheckpointDownEdit(index) {
  if (index === editingCheckpointTemplate.value.checkpoints.length - 1) return;
  const temp = editingCheckpointTemplate.value.checkpoints[index];
  editingCheckpointTemplate.value.checkpoints[index] = editingCheckpointTemplate.value.checkpoints[index + 1];
  editingCheckpointTemplate.value.checkpoints[index + 1] = temp;
  // Update orders
  editingCheckpointTemplate.value.checkpoints.forEach((cp, idx) => {
    cp.order = idx + 1;
  });
}

function closeNewMovementTemplateModal() {
  showNewMovementTemplate.value = false;
  movementTemplateErrors.value = {};
  selectedCheckpointTemplateId.value = null;
  newLeg.value = {
    from_location: '',
    to_location: '',
    leg_type: 'transfer',
    transport_type: 'bus',
    estimated_duration_minutes: null,
  };
}

function closeEditMovementTemplateModal() {
  showEditMovementTemplate.value = false;
  movementTemplateErrors.value = {};
  selectedCheckpointTemplateIdEdit.value = null;
  newLegEdit.value = {
    from_location: '',
    to_location: '',
    leg_type: 'transfer',
    transport_type: 'bus',
    estimated_duration_minutes: null,
  };
}

// Movement template leg builder functions
function addLegToTemplate() {
  if (!selectedCheckpointTemplateId.value || !newLeg.value.from_location || !newLeg.value.to_location) {
    return;
  }
  
  const nextOrder = newMovementTemplate.value.legs.length + 1;
  newMovementTemplate.value.legs.push({
    order: nextOrder,
    from_location: newLeg.value.from_location,
    to_location: newLeg.value.to_location,
    leg_type: newLeg.value.leg_type,
    checkpoint_template_id: selectedCheckpointTemplateId.value,
    transport_type: newLeg.value.transport_type,
    estimated_duration_minutes: newLeg.value.estimated_duration_minutes,
  });
  
  // Reset form
  selectedCheckpointTemplateId.value = null;
  newLeg.value = {
    from_location: '',
    to_location: '',
    leg_type: 'transfer',
    transport_type: 'bus',
    estimated_duration_minutes: null,
  };
}

function removeLegFromTemplate(index) {
  newMovementTemplate.value.legs.splice(index, 1);
  // Re-order remaining legs
  newMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

function moveLegUp(index) {
  if (index === 0) return;
  const temp = newMovementTemplate.value.legs[index];
  newMovementTemplate.value.legs[index] = newMovementTemplate.value.legs[index - 1];
  newMovementTemplate.value.legs[index - 1] = temp;
  // Update orders
  newMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

function moveLegDown(index) {
  if (index === newMovementTemplate.value.legs.length - 1) return;
  const temp = newMovementTemplate.value.legs[index];
  newMovementTemplate.value.legs[index] = newMovementTemplate.value.legs[index + 1];
  newMovementTemplate.value.legs[index + 1] = temp;
  // Update orders
  newMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

function getCheckpointTemplateName(templateId) {
  const template = props.checkpointTemplates.find(t => t.id === templateId);
  return template ? template.name : 'Unknown';
}

function getCheckpointTemplateCode(templateId) {
  const template = props.checkpointTemplates.find(t => t.id === templateId);
  return template ? template.code : '';
}

function getCheckpointTemplateById(templateId) {
  return props.checkpointTemplates.find(t => t.id === templateId);
}

function selectMovementTemplate(template) {
  selectedMovementTemplate.value = template;
}

// Movement template leg builder functions for editing
function addLegToEditTemplate() {
  if (!selectedCheckpointTemplateIdEdit.value || !newLegEdit.value.from_location || !newLegEdit.value.to_location) {
    return;
  }
  
  const nextOrder = editingMovementTemplate.value.legs.length + 1;
  editingMovementTemplate.value.legs.push({
    order: nextOrder,
    from_location: newLegEdit.value.from_location,
    to_location: newLegEdit.value.to_location,
    leg_type: newLegEdit.value.leg_type,
    checkpoint_template_id: selectedCheckpointTemplateIdEdit.value,
    transport_type: newLegEdit.value.transport_type,
    estimated_duration_minutes: newLegEdit.value.estimated_duration_minutes,
  });
  
  // Reset form
  selectedCheckpointTemplateIdEdit.value = null;
  newLegEdit.value = {
    from_location: '',
    to_location: '',
    leg_type: 'transfer',
    transport_type: 'bus',
    estimated_duration_minutes: null,
  };
}

function removeLegFromEditTemplate(index) {
  editingMovementTemplate.value.legs.splice(index, 1);
  // Re-order remaining legs
  editingMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

function moveLegUpEdit(index) {
  if (index === 0) return;
  const temp = editingMovementTemplate.value.legs[index];
  editingMovementTemplate.value.legs[index] = editingMovementTemplate.value.legs[index - 1];
  editingMovementTemplate.value.legs[index - 1] = temp;
  // Update orders
  editingMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

function moveLegDownEdit(index) {
  if (index === editingMovementTemplate.value.legs.length - 1) return;
  const temp = editingMovementTemplate.value.legs[index];
  editingMovementTemplate.value.legs[index] = editingMovementTemplate.value.legs[index + 1];
  editingMovementTemplate.value.legs[index + 1] = temp;
  // Update orders
  editingMovementTemplate.value.legs.forEach((leg, idx) => {
    leg.order = idx + 1;
  });
}

// Validation functions
function validateCheckpoint(checkpoint) {
  const errors = {};
  if (!checkpoint.name?.trim()) errors.name = 'Name is required';
  if (!checkpoint.type) errors.type = 'Type is required';
  if (!checkpoint.capture_method) errors.capture_method = 'Capture method is required';
  return errors;
}

function validateCheckpointTemplate(template) {
  const errors = {};
  if (!template.code?.trim()) errors.code = 'Code is required';
  if (!template.name?.trim()) errors.name = 'Name is required';
  if (!template.movement_type) errors.movement_type = 'Movement type is required';
  return errors;
}

function validateMovementTemplate(template) {
  const errors = {};
  if (!template.code?.trim()) errors.code = 'Code is required';
  if (!template.name?.trim()) errors.name = 'Name is required';
  if (!template.scenario_type) errors.scenario_type = 'Scenario type is required';
  return errors;
}

// CRUD functions for Checkpoints
function createCheckpoint() {
  checkpointErrors.value = validateCheckpoint(newCheckpoint.value);
  if (Object.keys(checkpointErrors.value).length > 0) {
    return;
  }

  processingCheckpoint.value = true;
  router.post('/admin/checkpoints', newCheckpoint.value, {
    onSuccess: () => {
      showNewCheckpoint.value = false;
      newCheckpoint.value = {
        name: '',
        type: 'dispatch',
        capture_method: 'manual',
        requires_photo: false,
        requires_signature: false,
        is_active: true,
      };
      checkpointErrors.value = {};
      showSuccessToast('Checkpoint created successfully');
    },
    onError: (errors) => {
      checkpointErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingCheckpoint.value = false;
    },
  });
}

function editCheckpoint(checkpoint) {
  editingCheckpoint.value = { ...checkpoint };
  checkpointErrors.value = {};
  showEditCheckpoint.value = true;
}

function updateCheckpoint() {
  checkpointErrors.value = validateCheckpoint(editingCheckpoint.value);
  if (Object.keys(checkpointErrors.value).length > 0) {
    return;
  }

  processingCheckpoint.value = true;
  router.put(`/admin/checkpoints/${editingCheckpoint.value.id}`, editingCheckpoint.value, {
    onSuccess: () => {
      showEditCheckpoint.value = false;
      editingCheckpoint.value = null;
      checkpointErrors.value = {};
      showSuccessToast('Checkpoint updated successfully');
    },
    onError: (errors) => {
      checkpointErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingCheckpoint.value = false;
    },
  });
}

function deleteCheckpoint(id) {
  const checkpoint = props.checkpoints.find(c => c.id === id);
  deleteTarget.value = {
    type: 'checkpoint',
    id: id,
    name: checkpoint?.name || 'this checkpoint',
    entityType: 'Checkpoint'
  };
  showDeleteConfirmation.value = true;
}

// CRUD functions for Checkpoint Templates
function createCheckpointTemplate() {
  checkpointTemplateErrors.value = validateCheckpointTemplate(newCheckpointTemplate.value);
  if (Object.keys(checkpointTemplateErrors.value).length > 0) {
    return;
  }

  processingCheckpointTemplate.value = true;
  router.post('/admin/checkpoint-templates', newCheckpointTemplate.value, {
    onSuccess: () => {
      showNewCheckpointTemplate.value = false;
      newCheckpointTemplate.value = {
        code: '',
        name: '',
        movement_type: 'arrival',
        description: '',
        estimated_duration_minutes: null,
        is_active: true,
        checkpoints: [],
      };
      selectedCheckpointId.value = null;
      checkpointTemplateErrors.value = {};
      showSuccessToast('Checkpoint template created successfully');
    },
    onError: (errors) => {
      checkpointTemplateErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingCheckpointTemplate.value = false;
    },
  });
}

function editCheckpointTemplate(template) {
  // Transform template.checkpoints from relationship to builder format
  const checkpointsArray = template.checkpoints?.map(cp => ({
    checkpoint_id: cp.id,
    order: cp.pivot.order,
    is_required: cp.pivot.is_required,
    estimated_minutes: cp.pivot.estimated_minutes,
  })) || [];
  
  editingCheckpointTemplate.value = { 
    ...template,
    checkpoints: checkpointsArray
  };
  selectedCheckpointIdEdit.value = null;
  checkpointTemplateErrors.value = {};
  showEditCheckpointTemplate.value = true;
}

function updateCheckpointTemplate() {
  checkpointTemplateErrors.value = validateCheckpointTemplate(editingCheckpointTemplate.value);
  if (Object.keys(checkpointTemplateErrors.value).length > 0) {
    return;
  }

  processingCheckpointTemplate.value = true;
  // Send the full data including checkpoints
  router.put(`/admin/checkpoint-templates/${editingCheckpointTemplate.value.id}`, editingCheckpointTemplate.value, {
    onSuccess: () => {
      showEditCheckpointTemplate.value = false;
      editingCheckpointTemplate.value = null;
      checkpointTemplateErrors.value = {};
      showSuccessToast('Checkpoint template updated successfully');
    },
    onError: (errors) => {
      checkpointTemplateErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingCheckpointTemplate.value = false;
    },
  });
}

function deleteCheckpointTemplate(id) {
  const template = props.checkpointTemplates.find(t => t.id === id);
  deleteTarget.value = {
    type: 'checkpoint-template',
    id: id,
    name: template?.name || 'this checkpoint template',
    entityType: 'Checkpoint Template'
  };
  showDeleteConfirmation.value = true;
}

// CRUD functions for Movement Templates
function createMovementTemplate() {
  movementTemplateErrors.value = validateMovementTemplate(newMovementTemplate.value);
  if (Object.keys(movementTemplateErrors.value).length > 0) {
    return;
  }

  processingMovementTemplate.value = true;
  router.post('/admin/movement-templates', newMovementTemplate.value, {
    onSuccess: () => {
      showNewMovementTemplate.value = false;
      newMovementTemplate.value = {
        code: '',
        name: '',
        scenario_type: 'match_day',
        description: '',
        estimated_duration_minutes: null,
        is_active: true,
        legs: [],
      };
      selectedCheckpointTemplateId.value = null;
      newLeg.value = {
        from_location: '',
        to_location: '',
        transport_type: 'bus',
        estimated_duration_minutes: null,
      };
      movementTemplateErrors.value = {};
      showSuccessToast('Movement template created successfully');
    },
    onError: (errors) => {
      movementTemplateErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingMovementTemplate.value = false;
    },
  });
}

function editMovementTemplate(template) {
  // Transform template.legs from relationship to builder format if they exist
  const legsArray = template.legs?.map(leg => ({
    order: leg.order,
    from_location: leg.from_location,
    to_location: leg.to_location,
    leg_type: leg.leg_type || 'transfer', // Default to 'transfer' if missing
    checkpoint_template_id: leg.checkpoint_template_id,
    transport_type: leg.transport_type,
    estimated_duration_minutes: leg.estimated_duration_minutes,
  })) || [];
  
  // Create copy without legs, then add transformed legs
  const { legs, ...templateWithoutLegs } = template;
  editingMovementTemplate.value = { 
    ...templateWithoutLegs,
    legs: legsArray
  };
  selectedCheckpointTemplateIdEdit.value = null;
  newLegEdit.value = {
    from_location: '',
    to_location: '',
    leg_type: 'transfer',
    transport_type: 'bus',
    estimated_duration_minutes: null,
  };
  movementTemplateErrors.value = {};
  showEditMovementTemplate.value = true;
}

function updateMovementTemplate() {
  movementTemplateErrors.value = validateMovementTemplate(editingMovementTemplate.value);
  if (Object.keys(movementTemplateErrors.value).length > 0) {
    return;
  }

  processingMovementTemplate.value = true;
  router.put(`/admin/movement-templates/${editingMovementTemplate.value.id}`, editingMovementTemplate.value, {
    onSuccess: () => {
      showEditMovementTemplate.value = false;
      editingMovementTemplate.value = null;
      movementTemplateErrors.value = {};
      showSuccessToast('Movement template updated successfully');
    },
    onError: (errors) => {
      movementTemplateErrors.value = errors;
      // Show first error in toast
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message);
    },
    onFinish: () => {
      processingMovementTemplate.value = false;
    },
  });
}

function deleteMovementTemplate(id) {
  const template = props.movementTemplates.find(t => t.id === id);
  deleteTarget.value = {
    type: 'movement-template',
    id: id,
    name: template?.name || 'this movement template',
    entityType: 'Movement Template'
  };
  showDeleteConfirmation.value = true;
}

function confirmDelete() {
  if (!deleteTarget.value) return;

  processingDelete.value = true;
  const { type, id } = deleteTarget.value;
  
  const routes = {
    'checkpoint': `/admin/checkpoints/${id}`,
    'checkpoint-template': `/admin/checkpoint-templates/${id}`,
    'movement-template': `/admin/movement-templates/${id}`
  };

  router.delete(routes[type], {
    onSuccess: () => {
      showDeleteConfirmation.value = false;
      deleteTarget.value = null;
      showSuccessToast('Deleted successfully');
    },
    onError: () => {
      // Keep modal open on error so user can see the error message
    },
    onFinish: () => {
      processingDelete.value = false;
    },
  });
}

function cancelDelete() {
  showDeleteConfirmation.value = false;
  deleteTarget.value = null;
}

// Styling helpers
function checkpointTypePillStyle(type) {
  const colors = {
    dispatch: { bg: '#EFF6FF', text: '#1E40AF' },
    arrival: { bg: '#F0FDF4', text: '#166534' },
    boarding: { bg: '#FEF3C7', text: '#92400E' },
    departure: { bg: '#FCE7F3', text: '#9F1239' },
    handoff: { bg: '#F3E8FF', text: '#6B21A8' },
  };
  const color = colors[type] || { bg: '#F3F4F6', text: '#374151' };
  return `
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    background: ${color.bg};
    color: ${color.text};
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
  `;
}

function movementTypePillStyle(type) {
  const colors = {
    arrival: { bg: '#F0FDF4', text: '#166534' },
    departure: { bg: '#FCE7F3', text: '#9F1239' },
    transfer: { bg: '#EFF6FF', text: '#1E40AF' },
    training: { bg: '#FEF3C7', text: '#92400E' },
    match: { bg: '#F3E8FF', text: '#6B21A8' },
  };
  const color = colors[type] || { bg: '#F3F4F6', text: '#374151' };
  return `
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    background: ${color.bg};
    color: ${color.text};
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
  `;
}
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.tab {
  padding: 10px 14px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  color: var(--ink3);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: color 0.15s ease, border-bottom-color 0.15s ease;
}

.tab:hover { 
  color: var(--ink); 
}

.tab--active { 
  color: var(--accent); 
  border-bottom-color: var(--accent); 
}

.tab-count {
  background: var(--panel); 
  border: 1px solid var(--border);
  border-radius: 999px; 
  padding: 1px 6px; 
  font-size: 10px; 
  font-weight: 700;
  color: var(--ink3);
}

.page-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 13px;
  color: var(--ink2);
  margin: 0;
}

.page-header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.plan-table-card {
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--border);
  overflow: hidden;
}

/* Slide transition for detail panel */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.3s ease;
}

.slide-left-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.slide-left-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
