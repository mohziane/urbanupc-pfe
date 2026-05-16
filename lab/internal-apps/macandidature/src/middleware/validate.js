export function validate(schema, target = 'body') {
  return (req, res, next) => {
    const data = req[target];
    const r = schema.safeParse(data);
    if (!r.success) {
      return res.status(400).json({
        error: 'validation_failed',
        issues: r.error.issues.map((i) => ({ path: i.path, code: i.code })),
      });
    }
    req[target] = r.data;
    next();
  };
}
