const dateTime = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })
const date = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium' })

export function formatDateTime(value: string | null | undefined): string {
  return value ? dateTime.format(new Date(value)) : '—'
}

export function formatDate(value: string | null | undefined): string {
  return value ? date.format(new Date(`${value}T00:00:00`)) : '—'
}

/** football-data positions, in Spanish, ordered as on the pitch. */
const POSITIONS: Record<string, { label: string; order: number }> = {
  Goalkeeper: { label: 'Arquero', order: 1 },
  Defence: { label: 'Defensa', order: 2 },
  'Centre-Back': { label: 'Defensa central', order: 2 },
  'Left-Back': { label: 'Lateral izquierdo', order: 2 },
  'Right-Back': { label: 'Lateral derecho', order: 2 },
  Midfield: { label: 'Mediocampo', order: 3 },
  'Defensive Midfield': { label: 'Mediocampista defensivo', order: 3 },
  'Central Midfield': { label: 'Mediocampista central', order: 3 },
  'Attacking Midfield': { label: 'Mediocampista ofensivo', order: 3 },
  'Left Midfield': { label: 'Volante izquierdo', order: 3 },
  'Right Midfield': { label: 'Volante derecho', order: 3 },
  Offence: { label: 'Delantero', order: 4 },
  'Left Winger': { label: 'Extremo izquierdo', order: 4 },
  'Right Winger': { label: 'Extremo derecho', order: 4 },
  'Centre-Forward': { label: 'Centrodelantero', order: 4 },
}

export function positionLabel(position: string | null | undefined): string {
  if (!position) return 'Sin posición'
  return POSITIONS[position]?.label ?? position
}

export function positionOrder(position: string | null | undefined): number {
  return (position && POSITIONS[position]?.order) || 9
}
