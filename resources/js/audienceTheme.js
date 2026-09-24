/**
 * Смена версии сайта — это переход на другой домен, то есть полная перезагрузка.
 * Чтобы она не выглядела рывком, уходящая страница заливается цветом той версии,
 * куда идём, а приходящая начинает с того же цвета и проявляет контент.
 *
 * Цвета должны совпадать с фоном главной в main.css и с тем, что подставляет
 * app.blade.php до загрузки стилей.
 */
export const AUDIENCE_COLOR = Object.freeze({
  patient: '#cac9bf',
  doctor: '#cba98e',
})

/** Метка «пришли через переключатель». Снимается с адреса после проявления. */
export const SWITCH_FLAG = 'from=switch'

const VEIL_ID = 'audience-veil'

export function showAudienceVeil(color) {
  if (typeof document === 'undefined') return null

  let veil = document.getElementById(VEIL_ID)
  if (!veil) {
    veil = document.createElement('div')
    veil.id = VEIL_ID
    document.body.appendChild(veil)
  }
  veil.style.backgroundColor = color
  // Принудительный рефлоу, иначе переход не стартует: элемент добавлен в том же кадре.
  void veil.getBoundingClientRect()
  veil.classList.add('is-visible')
  return veil
}

export function hideAudienceVeil() {
  if (typeof document === 'undefined') return
  const veil = document.getElementById(VEIL_ID)
  if (veil) {
    veil.classList.remove('is-visible')
  }
}
