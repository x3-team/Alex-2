// Единый справочник сетей, где можно сдать тест. Раньше список был скопирован в пять
// компонентов и разошёлся: Ситилаб местами вёл на «#», у ДНКОМ в поиске был домен dncom.ru.
// id — стабильный ключ для целей Метрики/GA (partner_id), его не менять при смене названия.
export const PARTNER_LABS = Object.freeze([
    { id: 'gemotest', name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
    { id: 'dnkom', name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
    { id: 'citilab', name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: 'https://citilab.ru/moskva/catalog/allergochip_alex2_allergy_explorer_292/allergochip_alex2_issledovanie_urovney_allergen_spetsificheskikh_immunoglobulinov_klassa_e_ige_k_300/' },
    { id: 'kdl', name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
    { id: 'cmd', name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
    { id: 'chromolab', name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' },
])

const CLINIC_UTM = 'utm_source=site&utm_medium=cta&utm_campaign=clinic'

// Адрес перехода к партнёру с нашей UTM-меткой; null, если адреса нет.
export function partnerUrl(lab) {
    const href = lab?.href
    if (!href || !/^https:\/\//.test(href)) return null
    if (href.includes('utm_source=')) return href
    return href + (href.includes('?') ? '&' : '?') + CLINIC_UTM
}
