const fechaHora = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })
const soloFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium' })

export function formatearFechaHora(valor: string | null | undefined): string {
  return valor ? fechaHora.format(new Date(valor)) : '—'
}

export function formatearFecha(valor: string | null | undefined): string {
  return valor ? soloFecha.format(new Date(`${valor}T00:00:00`)) : '—'
}

/** Posiciones de football-data, en español y ordenadas como en la cancha. */
const POSICIONES: Record<string, { etiqueta: string; orden: number }> = {
  Goalkeeper: { etiqueta: 'Arquero', orden: 1 },
  Defence: { etiqueta: 'Defensa', orden: 2 },
  'Centre-Back': { etiqueta: 'Defensa central', orden: 2 },
  'Left-Back': { etiqueta: 'Lateral izquierdo', orden: 2 },
  'Right-Back': { etiqueta: 'Lateral derecho', orden: 2 },
  Midfield: { etiqueta: 'Mediocampo', orden: 3 },
  'Defensive Midfield': { etiqueta: 'Mediocampista defensivo', orden: 3 },
  'Central Midfield': { etiqueta: 'Mediocampista central', orden: 3 },
  'Attacking Midfield': { etiqueta: 'Mediocampista ofensivo', orden: 3 },
  'Left Midfield': { etiqueta: 'Volante izquierdo', orden: 3 },
  'Right Midfield': { etiqueta: 'Volante derecho', orden: 3 },
  Offence: { etiqueta: 'Delantero', orden: 4 },
  'Left Winger': { etiqueta: 'Extremo izquierdo', orden: 4 },
  'Right Winger': { etiqueta: 'Extremo derecho', orden: 4 },
  'Centre-Forward': { etiqueta: 'Centrodelantero', orden: 4 },
}

export function etiquetaPosicion(posicion: string | null | undefined): string {
  if (!posicion) return 'Sin posición'
  return POSICIONES[posicion]?.etiqueta ?? posicion
}

export function ordenPosicion(posicion: string | null | undefined): number {
  return (posicion && POSICIONES[posicion]?.orden) || 9
}
