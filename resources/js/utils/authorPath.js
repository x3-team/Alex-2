export function authorPublicPath(author) {
  if (!author) return ''
  const key = author.slug || author.id
  if (key === undefined || key === null || key === '') return ''
  return `/blog/authors/${key}`
}
