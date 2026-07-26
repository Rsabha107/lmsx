# Movement Types Guide

## Overview
This guide defines the six movement types in the LMSX system and when to use each one.

---

## Movement Type Definitions

### 1. **Arrival** ✈️
**Purpose:** Team arriving at destination city from airport

**When to use:**
- Airport pickup and transfer to hotel
- Includes baggage handling and customs clearance
- Team entering the competition venue city

**Key characteristics:**
- Linked to flight data (flight_id)
- Reference time = flight arrival time
- Typically includes luggage loading checkpoint
- Higher checkpoint count (7 checkpoints standard)

**Examples:**
- Team Brazil flight arrives at CDG → transfer to Hotel Le Meridien
- Officials delegation arriving from Madrid → hotel check-in

---

### 2. **Departure** 🛫
**Purpose:** Team leaving competition city to airport

**When to use:**
- Hotel checkout and transfer to airport
- Team exiting the competition venue city
- Includes baggage handling

**Key characteristics:**
- Linked to flight data (flight_id)
- Reference time = flight departure time
- Typically includes luggage handling checkpoints
- Must account for check-in time (usually 2-3 hours before departure)

**Examples:**
- Team elimination: Hotel Solene → Airport for flight back home
- Officials departure after tournament completion

---

### 3. **Match** ⚽
**Purpose:** Transportation specifically for match day activities

**When to use:**
- Pre-match: Hotel/training ground → stadium
- Post-match: Stadium → hotel/airport
- Any movement directly related to a scheduled match

**Key characteristics:**
- Linked to match data (match_id)
- Reference time = match kick-off time
- Strict timing windows (must arrive X minutes before kick-off)
- Includes both playing teams and officials

**Examples:**
- Team Spain: Hotel → Stadium 2 hours before kick-off
- Referee crew: Hotel → Venue 90 minutes before kick-off
- Post-match: Stadium → Mixed zone → Hotel

---

### 4. **Training** 🏃
**Purpose:** Regular training session transportation

**When to use:**
- Hotel → training ground (outbound)
- Training ground → hotel (return)
- Recovery sessions at training facilities

**Key characteristics:**
- Recurring daily/regular schedule
- Standard route and timing
- Full squad typically travels together
- Predictable passenger count

**Examples:**
- Team Germany: Hotel → Training Ground A (09:00 departure daily)
- Recovery session: Hotel → Training Center (morning session)
- Tactical training: Hotel → Practice pitch → Hotel

---

### 5. **Transfer** 🚌
**Purpose:** General point-to-point transportation for event-driven needs

**When to use:**
- One-time or event-specific movements
- Not covered by the specific types above
- Ad-hoc transportation requests
- Media, promotional, or administrative movements

**Key characteristics:**
- Event-driven (not routine)
- Flexible routes and timing
- Variable passenger counts
- Part of a larger plan or event

**Examples:**
- Media day: Hotel → Press conference venue
- Fan engagement: Hotel → Fan zone event
- Medical: Hotel → Hospital visit
- Administrative: Hotel → Embassy visit
- Sponsor event: Hotel → Sponsor headquarters

**When NOT to use:**
- ❌ Daily recurring activities → use **daily_ops**
- ❌ Airport movements → use **arrival/departure**
- ❌ Match-related → use **match**
- ❌ Training activities → use **training**

---

### 6. **Daily Ops** 🔄
**Purpose:** Recurring routine operational movements

**When to use:**
- Regularly scheduled daily activities
- Ongoing logistical support operations
- Routine service runs
- Administrative and support movements

**Key characteristics:**
- **Recurring** (daily, multiple times per day)
- **Routine** (not event-specific)
- **Operational** (supporting infrastructure)
- Consistent schedule and route
- Typically support staff, not teams

**Examples:**
- Daily meal deliveries: Catering depot → Team hotels
- Equipment runs: Kit storage → Training grounds (daily)
- Staff shuttles: Operations center → Multiple venues (hourly)
- Supply restocking: Warehouse → Stadiums (twice daily)
- Medical supply runs: Medical center → Team hotels
- Laundry service: Hotels → Laundry facility → Hotels (daily)
- Media equipment: Broadcasting center → Venues (pre-scheduled)

**When NOT to use:**
- ❌ One-time movements → use **transfer**
- ❌ Team transportation → use appropriate team type
- ❌ Event-driven movements → use **transfer**

---

## Decision Tree

```
Is this movement related to a flight?
├─ YES, arriving → ARRIVAL
└─ YES, departing → DEPARTURE

Is this movement for a match?
└─ YES → MATCH

Is this movement for training?
└─ YES → TRAINING

Is this movement recurring/routine?
├─ YES, daily operational → DAILY_OPS
└─ NO, one-time/event-driven → TRANSFER
```

---

## Key Differences: Transfer vs Daily Ops

| Aspect | Transfer | Daily Ops |
|--------|----------|-----------|
| **Frequency** | One-time or occasional | Daily/recurring |
| **Schedule** | Event-driven | Fixed routine |
| **Purpose** | Specific event/need | Ongoing operations |
| **Passengers** | Teams, officials, VIPs | Staff, supplies, support |
| **Planning** | Part of event plan | Operational schedule |
| **Examples** | Media event, hospital visit | Meal delivery, equipment runs |

---

## Badge Colors in UI

- **Arrival**: Green (#F0FDF4 / #166534)
- **Departure**: Pink (#FCE7F3 / #9F1239)
- **Transfer**: Blue (#EFF6FF / #1E40AF)
- **Training**: Yellow (#FEF3C7 / #92400E)
- **Daily Ops**: Purple (#E9D5FF / #6B21A8)
- **Match**: Purple (#F3E8FF / #6B21A8)

---

## Best Practices

### For Planners:
1. **Be specific** - Use the most specific type available
2. **Think recurring** - If it happens daily, it's probably daily_ops
3. **Consider the passenger** - Teams = specific types, support = daily_ops
4. **Link data** - Arrivals/departures should link flights, matches should link games

### For Developers:
1. **Validate links** - Arrival/departure requires flight_id, match requires match_id
2. **Reference times** - Each type has specific reference time rules
3. **Checkpoint templates** - Different types have different checkpoint sequences
4. **Reporting** - Filter and group by type for meaningful insights

---

## Common Mistakes to Avoid

❌ **Wrong:** Using "transfer" for daily meal deliveries
✅ **Right:** Use "daily_ops" for recurring operational tasks

❌ **Wrong:** Using "daily_ops" for a one-time sponsor event
✅ **Right:** Use "transfer" for event-specific movements

❌ **Wrong:** Using "transfer" for match-day team transport
✅ **Right:** Use "match" for all match-related movements

❌ **Wrong:** Using "training" for a team visit to a sponsor facility
✅ **Right:** Use "transfer" for non-training team movements

---

## Questions?

If you're unsure which type to use, ask yourself:

1. **Is there a flight?** → arrival/departure
2. **Is there a match?** → match
3. **Is this training?** → training
4. **Does this happen every day?** → daily_ops
5. **Is this a one-time event?** → transfer

When in doubt, use **transfer** for team/VIP movements and **daily_ops** for recurring operational tasks.
